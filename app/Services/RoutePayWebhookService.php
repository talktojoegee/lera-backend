<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\Lga;
use App\Models\RoutePayQrRegistration;
use App\Traits\UtilityTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoutePayWebhookService
{
    use UtilityTrait;

    /**
     * Verify RoutePay webhook HMAC signature (merchantId:timestamp:jsonBody).
     * Uses the raw request body so signatures match curl/RoutePay payloads exactly
     * (re-encoding parsed JSON changes floats like 50000.00 → 50000).
     *
     * @see https://developer.routepay.com/?view=overview#webhook-url-configuration
     */
    public function verifySignature(Request $request, string $webhookSecret): bool
    {
        return $this->diagnoseSignatureFailure(
            $request,
            $webhookSecret,
            $this->merchantIdFromRequest($request)
        )['valid'];
    }

    /**
     * @return array{valid: bool, reason: string}
     */
    public function diagnoseSignatureFailure(Request $request, string $webhookSecret, string $merchantId): array
    {
        $signature = (string) $request->header('x-webhook-signature', '');
        $timestamp = (string) $request->header('x-webhook-timestamp', '');

        if ($signature === '') {
            return ['valid' => false, 'reason' => 'Missing x-webhook-signature header'];
        }

        if ($timestamp === '') {
            return ['valid' => false, 'reason' => 'Missing x-webhook-timestamp header'];
        }

        if ($webhookSecret === '') {
            return ['valid' => false, 'reason' => 'No webhook secret configured for merchant'];
        }

        if (! ctype_digit($timestamp)) {
            return ['valid' => false, 'reason' => 'x-webhook-timestamp must be Unix seconds (digits only)'];
        }

        if (! $this->isTimestampWithinTolerance($timestamp)) {
            $tolerance = max(60, (int) config('routepay.webhook_timestamp_tolerance', 300));

            return [
                'valid' => false,
                'reason' => 'x-webhook-timestamp is outside the '.$tolerance.'s tolerance — regenerate timestamp and signature',
            ];
        }

        if ($merchantId === '') {
            return ['valid' => false, 'reason' => 'merchantId not found — add header x-merchant-id (agency payment_gateway_client_id) or data.merchantId in body'];
        }

        $rawBody = $request->getContent();
        $jsonBody = $rawBody !== ''
            ? $rawBody
            : json_encode($request->all(), JSON_UNESCAPED_SLASHES);

        $payloadString = $merchantId.':'.$timestamp.':'.$jsonBody;
        $expected = hash_hmac('sha256', $payloadString, $webhookSecret);

        if (! hash_equals($expected, $signature)) {
            return [
                'valid' => false,
                'reason' => 'HMAC mismatch — sign the exact raw JSON body bytes (no pretty-print, no trailing newline)',
            ];
        }

        return ['valid' => true, 'reason' => ''];
    }

    protected function merchantIdFromRequest(Request $request): string
    {
        $payload = $request->all();
        $merchantId = (string) ($payload['merchantId'] ?? $request->header('x-merchant-id', ''));

        if ($merchantId !== '') {
            return $merchantId;
        }

        $rawBody = $request->getContent();
        if ($rawBody === '') {
            return '';
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            return '';
        }

        $merchantId = (string) ($decoded['merchantId'] ?? '');
        if ($merchantId !== '') {
            return $merchantId;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return (string) ($decoded['data']['merchantId'] ?? '');
        }

        return '';
    }

    public function resolveAgencyByMerchantId(?string $merchantId): ?Lga
    {
        $merchantId = trim((string) $merchantId);
        if ($merchantId === '') {
            return null;
        }

        return Lga::query()
            ->where('payment_gateway_client_id', $merchantId)
            ->first();
    }

    /**
     * RoutePay signs webhooks with the merchant client id (same value as merchantId in the payload).
     * Do not fall back to a different global ROUTEPAY_CLIENT_ID when the payload names another merchant.
     */
    protected function resolveWebhookSecret(string $merchantId, ?Lga $agency): string
    {
        if ($agency !== null && ! empty($agency->payment_gateway_client_id)) {
            return (string) $agency->payment_gateway_client_id;
        }

        if ($merchantId !== '') {
            return $merchantId;
        }

        return (string) config('routepay.client_id');
    }

    /**
     * Log full webhook request for Postman replay (see storage/app/routepay-postman-last-request.json).
     */
    public function logWebhookRequestForPostman(
        Request $request,
        string $source = 'incoming',
        ?string $webhookSecret = null,
        bool $force = false,
    ): void {
        if (! $force && ! config('routepay.log_webhook_for_postman', false)) {
            return;
        }

        $rawBody = $request->getContent();
        $payload = $request->all();
        $merchantId = (string) ($payload['merchantId'] ?? $request->header('x-merchant-id', ''));
        if ($merchantId === '' && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $merchantId = (string) ($decoded['merchantId'] ?? '');
                if ($merchantId === '' && isset($decoded['data']) && is_array($decoded['data'])) {
                    $merchantId = (string) ($decoded['data']['merchantId'] ?? '');
                }
            }
        }
        if ($merchantId === '' && $rawBody !== '') {
            $normalized = $this->normalizeWebhookPayload(json_decode($rawBody, true) ?? []);
            $merchantId = $this->resolveMerchantIdFromSettlementPayload($normalized);
        }

        $timestamp = (string) $request->header('x-webhook-timestamp', '');
        $signature = (string) $request->header('x-webhook-signature', '');
        $jsonBody = $rawBody !== '' ? $rawBody : json_encode($payload, JSON_UNESCAPED_SLASHES);

        $expectedSignature = null;
        if ($webhookSecret !== null && $webhookSecret !== '' && $timestamp !== '' && $jsonBody !== '' && $merchantId !== '') {
            $expectedSignature = hash_hmac('sha256', $merchantId.':'.$timestamp.':'.$jsonBody, $webhookSecret);
        }

        $fullUrl = $request->fullUrl();
        if (! str_contains($fullUrl, '://')) {
            $fullUrl = rtrim((string) config('app.url'), '/').$request->getRequestUri();
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'x-webhook-signature' => $signature,
            'x-webhook-timestamp' => $timestamp,
        ];
        if ($merchantId !== '') {
            $headers['x-merchant-id'] = $merchantId;
        }

        $postman = [
            'generated_at' => now()->toIso8601String(),
            'source' => $source,
            'postman' => [
                'method' => $request->method(),
                'url' => $fullUrl,
                'headers' => $headers,
                'body_raw' => $jsonBody,
                'body' => json_decode($jsonBody, true) ?? $payload,
            ],
            'signature_debug' => [
                'merchant_id' => $merchantId,
                'hmac_secret' => 'agency payment_gateway_client_id (same as merchantId in body)',
                'payload_string_format' => '{merchantId}:{x-webhook-timestamp}:{rawJsonBody}',
                'received_signature' => $signature,
                'expected_signature' => $expectedSignature,
                'signature_valid' => $expectedSignature !== null && $signature !== ''
                    ? hash_equals($expectedSignature, $signature)
                    : null,
            ],
            'postman_steps' => [
                '1' => 'Method: POST',
                '2' => 'URL: copy postman.url (includes ?event=payment)',
                '3' => 'Headers: add all keys under postman.headers',
                '4' => 'Body: raw JSON — paste postman.body_raw exactly (not pretty-printed reordered JSON)',
                '5' => 'If signature fails, regenerate x-webhook-timestamp and signature using signature_debug rules',
            ],
        ];

        $file = storage_path('app/routepay-postman-last-request.json');
        file_put_contents($file, json_encode($postman, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        Log::info('RoutePay webhook: Postman replay details', $postman);
    }

    /**
     * Record a payment from a verified RoutePay payload (webhook replay / manual reconcile).
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function processVerifiedPaymentPayload(array $payload, string $eventType = 'payment'): array
    {
        return $this->handlePaymentEvent($eventType, $this->normalizeWebhookPayload($payload));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handleWebhook(Request $request): array
    {
        $payload = $this->normalizeWebhookPayload($request->all());
        $eventType = (string) ($request->query('event', $payload['event'] ?? 'payment'));

        $merchantId = $this->resolveWebhookMerchantId($request, $payload);
        if ($merchantId !== '') {
            $payload['merchantId'] = $merchantId;
        }

        $agency = $this->resolveAgencyByMerchantId($merchantId);
        $webhookSecret = $this->resolveWebhookSecret($merchantId, $agency);

        $this->logWebhookRequestForPostman(
            $request,
            'incoming',
            $webhookSecret !== '' ? $webhookSecret : null,
            in_array($eventType, ['payment', 'payment.completed', 'payment.failed', 'settlement'], true)
        );

        if ($webhookSecret === '') {
            Log::warning('RoutePay webhook: no merchant client id to verify signature.', [
                'event' => $eventType,
                'merchant_id' => $merchantId,
            ]);

            return ['status' => 401, 'body' => ['error' => 'Unknown merchant']];
        }

        $signatureCheck = $this->diagnoseSignatureFailure($request, $webhookSecret, $merchantId);
        if (! $signatureCheck['valid']) {
            Log::warning('RoutePay webhook: signature verification failed.', [
                'event' => $eventType,
                'merchant_id' => $merchantId,
                'reason' => $signatureCheck['reason'],
                'webhook_secret_source' => $agency !== null
                    ? 'agency'
                    : ($merchantId !== '' ? 'payload_merchant_id' : 'global_env'),
                'hint' => 'HMAC-SHA256 over {merchantId}:{x-webhook-timestamp}:{rawJsonBody}. Secret = merchantId (payment_gateway_client_id). Body bytes must match exactly.',
            ]);

            return [
                'status' => 401,
                'body' => [
                    'error' => 'Invalid signature',
                    'reason' => $signatureCheck['reason'],
                ],
            ];
        }

        $result = match ($eventType) {
            'payment', 'payment.completed', 'payment.failed' => $this->handlePaymentEvent($eventType, $payload),
            'credit', 'debit', 'payout', 'settlement' => $this->handleLedgerEvent($eventType, $payload),
            default => $this->acknowledgeUnhandledEvent($eventType, $payload),
        };

        if (in_array($eventType, ['payment', 'payment.completed', 'payment.failed', 'settlement'], true)) {
            Log::info('RoutePay webhook: payment event handled.', [
                'event' => $eventType,
                'merchant_id' => $merchantId,
                'merchant_reference' => $payload['merchantReference'] ?? null,
                'transaction_id' => $payload['transactionId'] ?? $payload['transactionReference'] ?? null,
                'payment_status' => $payload['paymentStatus'] ?? $payload['status'] ?? null,
                'http_status' => $result['status'],
                'response' => $result['body'],
            ]);
        }

        return $result;
    }

    /**
     * Resolve merchant id for HMAC verification (settlement webhooks often omit merchantId in JSON).
     *
     * @param  array<string, mixed>  $payload  Normalized payload (data.* merged to top level).
     */
    protected function resolveWebhookMerchantId(Request $request, array $payload): string
    {
        $merchantId = trim((string) (
            $payload['merchantId']
            ?? $request->header('x-merchant-id')
            ?? ''
        ));
        if ($merchantId !== '') {
            return $merchantId;
        }

        $merchantId = trim($this->merchantIdFromRequest($request));
        if ($merchantId !== '') {
            return $merchantId;
        }

        return $this->resolveMerchantIdFromSettlementPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function handlePaymentEvent(string $eventType, array $payload): array
    {
        $merchantReference = trim((string) ($payload['merchantReference'] ?? ''));
        $transactionReference = trim((string) ($payload['transactionId'] ?? $payload['transactionReference'] ?? ''));

        $registration = $this->findQrRegistration($merchantReference, $transactionReference, $payload);

        $registration?->update([
            'webhook_status' => (string) ($payload['paymentStatus'] ?? $payload['status'] ?? $eventType),
            'transaction_reference' => $transactionReference !== ''
                ? $transactionReference
                : $registration->transaction_reference,
        ]);

        if (! $this->isPaymentSuccessful($payload)) {
            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => false,
                    'reason' => 'Payment not in successful state',
                ],
            ];
        }

        $transRef = $transactionReference !== '' ? $transactionReference : $merchantReference;

        if ($this->findOrphanDonationLog($transRef) !== null) {
            $reclassified = $this->reclassifyDonationPayment($transRef, $payload);
            if ($reclassified['processed'] ?? false) {
                return [
                    'status' => 200,
                    'body' => array_merge($reclassified, [
                        'status' => 'received',
                        'event' => $eventType,
                    ]),
                ];
            }
        }

        $bill = $this->resolvePaymentBill($registration, $merchantReference, $payload, $transactionReference);

        if ($bill !== null && $registration === null) {
            $registration = $this->findQrRegistration($merchantReference, $transactionReference, $payload)
                ?? $this->findQrRegistrationForSettlement($payload, $transactionReference);
        }

        if ($bill === null) {
            if ($this->isTransactionAlreadyAppliedToBill($transRef, null)) {
                return [
                    'status' => 200,
                    'body' => [
                        'status' => 'received',
                        'event' => $eventType,
                        'processed' => true,
                        'reason' => 'Already processed',
                    ],
                ];
            }

            if ($this->findOrphanDonationLog($transRef) !== null) {
                return [
                    'status' => 200,
                    'body' => [
                        'status' => 'received',
                        'event' => $eventType,
                        'processed' => true,
                        'payment_type' => 'donation',
                        'reason' => 'Already processed',
                        'assessment_no' => 'DONATION',
                    ],
                ];
            }

            $diagRegistration = $registration
                ?? $this->findQrRegistrationForSettlement($payload, $transactionReference);

            $referenceForAssessment = $merchantReference !== ''
                ? $merchantReference
                : trim((string) ($diagRegistration?->merchant_reference ?? ''));

            if ($referenceForAssessment !== '' && $this->assessmentPresentInReference($referenceForAssessment)) {
                if ($diagRegistration !== null) {
                    $billFromRegistration = $this->resolveBillForWebhookPayment($diagRegistration, $referenceForAssessment);
                    if ($billFromRegistration !== null && $this->billHasRequiredAssessment($billFromRegistration)) {
                        if ($this->isTransactionAlreadyAppliedToBill($transRef, $billFromRegistration->id)) {
                            return [
                                'status' => 200,
                                'body' => [
                                    'status' => 'received',
                                    'event' => $eventType,
                                    'processed' => true,
                                    'reason' => 'Already processed',
                                    'assessment_no' => $billFromRegistration->assessment_no,
                                    'bill_id' => $billFromRegistration->id,
                                ],
                            ];
                        }

                        return $this->applyWebhookBillPayment(
                            $eventType,
                            $payload,
                            $billFromRegistration,
                            $transRef,
                            $diagRegistration
                        );
                    }
                }

                Log::warning('RoutePay webhook: assessment in reference but no bill matched — payment not recorded.', [
                    'merchant_reference' => $referenceForAssessment,
                    'transaction_reference' => $transactionReference,
                    'registration_bill_id' => $diagRegistration?->bill_id,
                    'registration_assessment_no' => $diagRegistration?->assessment_no,
                    'registration_merchant_reference' => $diagRegistration?->merchant_reference,
                    'settled_amount' => $payload['settledAmount'] ?? $payload['transactionAmount'] ?? null,
                    'fee_amount' => $payload['feeAmount'] ?? null,
                    'merchant_id' => $payload['merchantId'] ?? null,
                    'settlement_time' => $this->resolveSettlementEventTime($payload, $transactionReference)?->toIso8601String(),
                    'match_hint' => 'Ensure route_pay_qr_registrations row exists for merchant_reference (e.g. from QR log) before payment',
                ]);

                return [
                    'status' => 200,
                    'body' => [
                        'status' => 'received',
                        'event' => $eventType,
                        'processed' => false,
                        'reason' => 'No bill with assessment number matched for this payment',
                        'transaction_reference' => $transactionReference,
                    ],
                ];
            }

            Log::info('RoutePay webhook: no assessment number in merchant reference — recording as donation.', [
                'merchant_reference' => $merchantReference,
                'transaction_reference' => $transactionReference,
                'registration_bill_id' => $diagRegistration?->bill_id,
                'registration_assessment_no' => $diagRegistration?->assessment_no,
            ]);

            return $this->applyWebhookDonation(
                $eventType,
                $payload,
                $transRef,
                $merchantReference,
                $diagRegistration
            );
        }

        if ($this->isTransactionAlreadyAppliedToBill($transRef, $bill->id)) {
            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => true,
                    'reason' => 'Already processed',
                    'assessment_no' => $bill->assessment_no,
                    'bill_id' => $bill->id,
                ],
            ];
        }

        return $this->applyWebhookBillPayment(
            $eventType,
            $payload,
            $bill,
            $transRef,
            $registration
        );
    }

    /**
     * Every RoutePay payment must map to a bill with a valid assessment number.
     */
    protected function resolvePaymentBill(
        ?RoutePayQrRegistration $registration,
        string $merchantReference,
        array $payload,
        string $transactionReference,
    ): ?Billing {
        if ($merchantReference !== '' && RoutePayQrService::isLeraMerchantReference($merchantReference)) {
            $registration = RoutePayQrRegistration::where('merchant_reference', $merchantReference)->first()
                ?? $registration;
        }

        $bill = $this->resolveBillForWebhookPayment($registration, $merchantReference);
        if ($bill !== null && $this->billHasRequiredAssessment($bill)) {
            return $bill;
        }

        $registration = $this->findQrRegistrationForSettlement($payload, $transactionReference) ?? $registration;

        if ($registration !== null) {
            $bill = $this->resolveBillForWebhookPayment($registration, $merchantReference);
            if ($bill !== null && $this->billHasRequiredAssessment($bill)) {
                return $bill;
            }
        }

        $assessmentFromRef = $this->extractAssessmentNumberFromReference($merchantReference);
        if ($assessmentFromRef !== null) {
            $bill = Billing::where('assessment_no', $assessmentFromRef)
                ->where('paid', 0)
                ->orderByDesc('year')
                ->first();

            if ($bill !== null) {
                return $bill;
            }
        }

        return null;
    }

    protected function billHasRequiredAssessment(Billing $bill): bool
    {
        $assessmentNo = trim((string) ($bill->assessment_no ?? ''));

        return $assessmentNo !== '' && strtoupper($assessmentNo) !== 'DONATION';
    }

    /**
     * Move a mistaken RoutePay Donation log onto the correct bill (settlement webhooks).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function reclassifyDonationPayment(
        string $transRef,
        array $payload = [],
        ?int $billId = null,
        ?string $assessmentNo = null,
    ): array {
        $transRef = trim($transRef);
        if ($transRef === '') {
            return ['processed' => false, 'reason' => 'Empty transaction reference'];
        }

        $donation = $this->findOrphanDonationLog($transRef);

        if ($donation === null) {
            return ['processed' => false, 'reason' => 'No donation log for this trans_ref'];
        }

        $merchantRef = trim((string) ($donation->reference ?? ''));
        $registration = RoutePayQrRegistration::query()
            ->where(function ($q) use ($transRef, $merchantRef) {
                $q->where('transaction_reference', $transRef);
                if ($merchantRef !== '' && $merchantRef !== $transRef) {
                    $q->orWhere('merchant_reference', $merchantRef);
                }
            })
            ->orderByDesc('id')
            ->first();

        if ($registration === null) {
            $registration = $this->findQrRegistrationForSettlement(
                array_merge($payload, [
                    'settledAmount' => $donation->amount,
                    'transactionAmount' => $donation->amount,
                    'amount' => $donation->amount,
                ]),
                $transRef
            );
        }

        $bill = $this->resolveBillForDonationRepair($registration, $billId, $assessmentNo);
        if ($bill === null || ! $this->billHasRequiredAssessment($bill)) {
            return [
                'processed' => false,
                'reason' => 'Could not resolve bill with assessment number — pass bill_id or assessment_no, or ensure route_pay_qr_registrations has bill_id',
            ];
        }

        if ($this->isTransactionAlreadyAppliedToBill($transRef, $bill->id)) {
            $donation->delete();
            $bill = $this->syncBillPaidAmountFromPaymentLogs($bill->fresh());

            return [
                'processed' => true,
                'reason' => 'Donation removed; bill payment already recorded',
                'bill_id' => $bill->id,
                'assessment_no' => $bill->assessment_no,
                'bill_paid_amount' => (float) $bill->paid_amount,
                'bill_paid' => (int) $bill->paid,
            ];
        }

        $amount = $this->resolveBillPaymentAmount($payload, $registration, $bill);

        try {
            DB::beginTransaction();

            $donation->delete();

            $property = $bill->getPropertyList;
            $paymentData = [
                'payment_code' => substr(sha1($transRef), 0, 12),
                'paid_by' => 5,
                'receipt_no' => 'RP'.substr(sha1($transRef.'reclassify'), 0, 14),
                'payment_ref' => $transRef,
                'assessment_no' => $bill->assessment_no,
                'bank_name' => 'RoutePay',
                'branch' => 'Webhook',
                'pay_mode' => 'RoutePay',
                'customer_name' => $property?->owner_name ?? 'Customer',
                'email' => $property?->owner_email ?? '',
                'kg_tin' => $property?->owner_kgtin ?? '',
                'trans_ref' => $transRef,
                'trans_date' => $donation->entry_date ?? now(),
                'phone_number' => $property?->owner_gsm ?? '',
            ];

            $applied = $this->handleSingleBillPayment($bill->fresh(), $amount, $paymentData);

            if ($applied <= 0) {
                DB::rollBack();

                return ['processed' => false, 'reason' => 'Bill has no outstanding balance'];
            }

            $registration?->update([
                'webhook_status' => 'reclassified_from_donation',
                'transaction_reference' => $transRef,
            ]);

            DB::commit();

            $bill->refresh();

            Log::info('RoutePay: reclassified donation log to bill payment.', [
                'trans_ref' => $transRef,
                'bill_id' => $bill->id,
                'amount' => $applied,
                'bill_paid_amount' => $bill->paid_amount,
                'bill_paid' => $bill->paid,
            ]);

            return [
                'processed' => true,
                'payment_type' => 'bill',
                'reason' => 'Reclassified from donation',
                'bill_id' => $bill->id,
                'assessment_no' => $bill->assessment_no,
                'amount' => $applied,
                'bill_paid_amount' => (float) $bill->paid_amount,
                'bill_paid' => (int) $bill->paid,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RoutePay reclassify donation failed.', [
                'trans_ref' => $transRef,
                'error' => $e->getMessage(),
            ]);

            return ['processed' => false, 'reason' => 'Reclassify failed: '.$e->getMessage()];
        }
    }

    protected function findOrphanDonationLog(string $transRef): ?BillPaymentLog
    {
        return BillPaymentLog::query()
            ->where('trans_ref', $transRef)
            ->where(function ($q) {
                $q->where('bill_master', 0)
                    ->orWhere('assessment_no', 'DONATION')
                    ->orWhere('pay_mode', 'RoutePay Donation');
            })
            ->first();
    }

    protected function resolveBillForDonationRepair(
        ?RoutePayQrRegistration $registration,
        ?int $billId,
        ?string $assessmentNo,
    ): ?Billing {
        if ($billId !== null && $billId > 0) {
            $bill = Billing::find($billId);
            if ($bill !== null) {
                return $bill;
            }
        }

        if ($registration?->bill_id) {
            $bill = Billing::find($registration->bill_id);
            if ($bill !== null) {
                return $bill;
            }
        }

        $assessmentNo = trim((string) ($assessmentNo ?? $registration?->assessment_no ?? ''));
        if ($assessmentNo !== '') {
            $bill = Billing::where('assessment_no', $assessmentNo)
                ->where('paid', 0)
                ->orderByDesc('year')
                ->first();

            if ($bill !== null) {
                return $bill;
            }

            return Billing::where('assessment_no', $assessmentNo)
                ->orderByDesc('year')
                ->first();
        }

        return null;
    }

    protected function resolveBillForWebhookPayment(
        ?RoutePayQrRegistration $registration,
        string $merchantReference,
    ): ?Billing {
        if ($registration?->bill_id) {
            $bill = Billing::find($registration->bill_id);
            if ($bill !== null) {
                return $bill;
            }
        }

        $assessmentNo = trim((string) ($registration?->assessment_no ?? ''));
        if ($assessmentNo !== '') {
            $bill = Billing::where('assessment_no', $assessmentNo)
                ->where('paid', 0)
                ->orderByDesc('year')
                ->first();
            if ($bill !== null) {
                return $bill;
            }

            $bill = Billing::where('assessment_no', $assessmentNo)
                ->orderByDesc('year')
                ->first();
            if ($bill !== null) {
                return $bill;
            }
        }

        $assessmentFromRef = $this->extractAssessmentNumberFromReference($merchantReference);
        if ($assessmentFromRef === null) {
            return null;
        }

        $bill = Billing::where('assessment_no', $assessmentFromRef)
            ->where('paid', 0)
            ->orderByDesc('year')
            ->first();
        if ($bill !== null) {
            return $bill;
        }

        return Billing::where('assessment_no', $assessmentFromRef)
            ->orderByDesc('year')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function applyWebhookBillPayment(
        string $eventType,
        array $payload,
        Billing $bill,
        string $transRef,
        ?RoutePayQrRegistration $registration,
    ): array {
        if (! $this->billHasRequiredAssessment($bill)) {
            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => false,
                    'reason' => 'Payment requires a valid assessment number',
                ],
            ];
        }

        $amount = $this->resolveBillPaymentAmount($payload, $registration, $bill);

        if ($amount <= 0) {
            Log::warning('RoutePay webhook: bill payment amount resolved to zero.', [
                'bill_id' => $bill->id,
                'assessment_no' => $bill->assessment_no,
                'bill_amount' => $bill->bill_amount,
                'paid_amount' => $bill->paid_amount,
                'single_bill_balance' => $this->billOutstandingBalance($bill),
                'registration_amount_expected' => $registration?->amount_expected,
                'webhook_gross' => $this->resolveWebhookGrossAmount($payload),
                'fee_amount' => $payload['feeAmount'] ?? null,
                'transaction_reference' => $transRef,
            ]);

            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => false,
                    'reason' => 'Payment amount is zero',
                    'assessment_no' => $bill->assessment_no,
                ],
            ];
        }

        try {
            DB::beginTransaction();

            $property = $bill->getPropertyList;
            $paymentData = [
                'payment_code' => substr(sha1($transRef), 0, 12),
                'paid_by' => 5,
                'receipt_no' => 'RP'.substr(sha1($transRef.time()), 0, 14),
                'payment_ref' => $transRef,
                'assessment_no' => $bill->assessment_no,
                'bank_name' => 'RoutePay',
                'branch' => 'Webhook',
                'pay_mode' => 'RoutePay',
                'customer_name' => $payload['customerName'] ?? $property?->owner_name ?? 'Customer',
                'email' => $payload['customerEmail'] ?? $property?->owner_email ?? ($bill->email ?? 'noreply@lera.local'),
                'kg_tin' => $property?->owner_kgtin ?? '',
                'trans_ref' => $transRef,
                'trans_date' => $payload['transactionDate'] ?? $payload['transactionDateTime'] ?? now(),
                'phone_number' => $payload['customerPhone'] ?? $property?->owner_gsm ?? '',
            ];

            $this->handleBillPayment($bill->building_code, $amount, $paymentData);

            $applied = (float) BillPaymentLog::where('trans_ref', $transRef)
                ->where('assessment_no', '!=', 'DONATION')
                ->where('pay_mode', '!=', 'RoutePay Donation')
                ->sum('amount');

            if ($applied <= 0) {
                DB::rollBack();

                Log::warning('RoutePay webhook: bill located but no amount applied.', [
                    'bill_id' => $bill->id,
                    'assessment_no' => $bill->assessment_no,
                    'bill_amount' => $bill->bill_amount,
                    'paid_amount_column' => $bill->paid_amount,
                    'requested_amount' => $amount,
                ]);

                return [
                    'status' => 200,
                    'body' => [
                        'status' => 'received',
                        'event' => $eventType,
                        'processed' => false,
                        'reason' => 'Bill has no outstanding balance to apply payment',
                        'assessment_no' => $bill->assessment_no,
                        'bill_id' => $bill->id,
                    ],
                ];
            }

            DB::commit();

            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => true,
                    'payment_type' => 'bill',
                    'assessment_no' => $bill->assessment_no,
                    'bill_id' => $bill->id,
                    'amount' => $applied,
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RoutePay webhook bill payment failed.', [
                'error' => $e->getMessage(),
                'assessment_no' => $bill->assessment_no,
                'bill_id' => $bill->id,
            ]);

            return ['status' => 500, 'body' => ['error' => 'Processing failed']];
        }
    }

    /**
     * Gross amount from RoutePay webhook (includes fees/surcharge).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveWebhookGrossAmount(array $payload): float
    {
        foreach (['amount', 'totalAmount', 'transactionAmount', 'settledAmount'] as $field) {
            if (! isset($payload[$field]) || $payload[$field] === null || $payload[$field] === '') {
                continue;
            }
            $amount = (float) $payload[$field];
            if ($amount > 0) {
                return $amount;
            }
        }

        return 0.0;
    }

    /**
     * Net bill credit — gross settlement minus RoutePay charges.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function netAmountExcludingCharges(
        float $gross,
        array $payload,
        ?RoutePayQrRegistration $registration = null,
    ): float {
        if ($gross <= 0) {
            return 0.0;
        }

        foreach (['feeAmount', 'fee', 'transactionFee', 'surcharge'] as $feeField) {
            $fee = (float) ($payload[$feeField] ?? 0);
            if ($fee > 0) {
                return max(0.0, round($gross - $fee, 2));
            }
        }

        $expected = (float) ($registration?->amount_expected ?? 0);
        if ($expected > 0 && $gross > $expected) {
            $delta = $gross - $expected;
            if ($delta <= max(10.0, $expected * 0.2)) {
                return $expected;
            }
        }

        return $gross;
    }

    protected function billOutstandingBalance(Billing $bill): float
    {
        $paidFromLogs = (float) BillPaymentLog::where('bill_master', $bill->id)
            ->where('assessment_no', '!=', 'DONATION')
            ->sum('amount');
        $paidSoFar = max((float) $bill->paid_amount, $paidFromLogs);

        return max(0.0, (float) $bill->bill_amount - $paidSoFar);
    }

    /**
     * Bill payment amount from RoutePay webhook — uses `amount` as-is (no fee deduction).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveBillPaymentAmount(
        array $payload,
        ?RoutePayQrRegistration $registration,
        ?Billing $bill = null,
    ): float {
        $expected = (float) ($registration?->amount_expected ?? 0);

        if (isset($payload['amount']) && $payload['amount'] !== null && $payload['amount'] !== '') {
            $net = (float) $payload['amount'];
        } else {
            $gross = $this->resolveWebhookGrossAmount($payload);

            if ($expected > 0 && $this->webhookAmountMatchesRegistration($gross > 0 ? $gross : $expected, $expected, $payload)) {
                $net = $expected;
            } elseif ($expected > 0 && $gross <= 0) {
                $net = $expected;
            } else {
                $net = $gross;
                if ($net <= 0 && $expected > 0) {
                    $net = $expected;
                }
            }
        }

        if ($bill !== null && $expected <= 0) {
            $balance = $this->billOutstandingBalance($bill);
            $net = min($net, $balance);
        }

        return max(0.0, round($net, 2));
    }

    /**
     * Whether the merchant reference string itself encodes an assessment (no DB lookup).
     */
    protected function assessmentPresentInReference(string $reference): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }

        if (RoutePayQrService::isLeraMerchantReference($reference)) {
            return true;
        }

        $upper = strtoupper($reference);

        if (preg_match('/(?:^|[^A-Z0-9])([A-Z0-9]{13})(?:[^A-Z0-9]|$)/', $upper)) {
            return true;
        }

        if (preg_match('/^([A-Z0-9]{4,})-[A-Z0-9]+$/i', $reference)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function applyWebhookDonation(
        string $eventType,
        array $payload,
        string $transRef,
        string $merchantReference,
        ?RoutePayQrRegistration $registration = null,
    ): array {
        $amount = $this->resolveWebhookGrossAmount($payload);
        if ($amount <= 0) {
            $amount = (float) ($registration?->amount_expected ?? 0);
        }

        if ($amount <= 0) {
            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => false,
                    'reason' => 'Donation amount is zero',
                ],
            ];
        }

        $merchantId = trim((string) ($payload['merchantId'] ?? ''));
        $agency = $this->resolveAgencyByMerchantId($merchantId);

        try {
            DB::beginTransaction();

            BillPaymentLog::create([
                'bill_master' => 0,
                'paid_by' => 5,
                'amount' => $amount,
                'receipt_no' => 'DN'.substr(sha1($transRef.time()), 0, 14),
                'payment_code' => substr(sha1($transRef), 0, 12),
                'assessment_no' => 'DONATION',
                'building_code' => '',
                'lga_id' => $agency?->id ?? $registration?->lga_id,
                'bank_name' => 'RoutePay',
                'branch_name' => 'Webhook',
                'pay_mode' => 'RoutePay Donation',
                'customer_name' => (string) ($payload['customerName'] ?? 'Donor'),
                'email' => (string) ($payload['customerEmail'] ?? ''),
                'kgtin' => '',
                'entry_date' => $payload['transactionDate'] ?? $payload['transactionDateTime'] ?? now(),
                'trans_ref' => $transRef,
                'reference' => $merchantReference !== '' ? $merchantReference : $transRef,
            ]);

            DB::commit();

            Log::info('RoutePay webhook: donation recorded.', [
                'trans_ref' => $transRef,
                'merchant_reference' => $merchantReference,
                'amount' => $amount,
                'lga_id' => $agency?->id ?? $registration?->lga_id,
            ]);

            return [
                'status' => 200,
                'body' => [
                    'status' => 'received',
                    'event' => $eventType,
                    'processed' => true,
                    'payment_type' => 'donation',
                    'assessment_no' => 'DONATION',
                    'amount' => $amount,
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RoutePay webhook donation recording failed.', [
                'error' => $e->getMessage(),
                'merchant_reference' => $merchantReference,
                'trans_ref' => $transRef,
            ]);

            return ['status' => 500, 'body' => ['error' => 'Processing failed']];
        }
    }

    /**
     * Pull an LERA assessment number out of the RoutePay merchant reference, if present.
     */
    protected function extractAssessmentNumberFromReference(string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        if (RoutePayQrService::isLeraMerchantReference($reference)) {
            $registration = RoutePayQrRegistration::where('merchant_reference', $reference)->first();
            $assessmentNo = trim((string) ($registration?->assessment_no ?? ''));
            if ($assessmentNo !== '') {
                return $assessmentNo;
            }
        }

        $upper = strtoupper($reference);

        if (preg_match('/(?:^|[^A-Z0-9])([A-Z0-9]{13})(?:[^A-Z0-9]|$)/', $upper, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^([A-Z0-9]+)-[A-Z0-9]+$/i', $reference, $matches)) {
            $candidate = strtoupper($matches[1]);
            if (Billing::where('assessment_no', $candidate)->exists()) {
                return $candidate;
            }

            $bill = Billing::where('assessment_no', 'like', $candidate.'%')
                ->where('paid', 0)
                ->orderByDesc('year')
                ->first();
            if ($bill !== null) {
                return $bill->assessment_no;
            }

            if (strlen($candidate) === 13) {
                return $candidate;
            }
        }

        if (Billing::where('assessment_no', $reference)->exists()) {
            return $reference;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function handleLedgerEvent(string $eventType, array $payload): array
    {
        if ($eventType === 'settlement' && $this->isSettlementSuccessful($payload)) {
            $transactionReference = trim((string) ($payload['transactionReference'] ?? ''));
            if ($transactionReference !== '') {
                Log::info('RoutePay webhook: settlement received — attempting payment match by transaction reference.', [
                    'transaction_reference' => $transactionReference,
                ]);

                return $this->handlePaymentEvent('payment', array_merge($payload, [
                    'transactionId' => $transactionReference,
                    'transactionReference' => $transactionReference,
                    'paymentStatus' => 'Successful',
                    'status' => $payload['status'] ?? $payload['Status'] ?? 'Success',
                    'amount' => $payload['settledAmount'] ?? $payload['transactionAmount'] ?? 0,
                    'settledAmount' => $payload['settledAmount'] ?? $payload['transactionAmount'] ?? 0,
                    'transactionAmount' => $payload['transactionAmount'] ?? $payload['settledAmount'] ?? 0,
                    'feeAmount' => $payload['feeAmount'] ?? $payload['fee'] ?? 0,
                ]));
            }
        }

        Log::info('RoutePay webhook ledger event received.', [
            'event' => $eventType,
            'transaction_reference' => $payload['transactionReference'] ?? null,
            'merchant_reference' => $payload['merchantReference'] ?? null,
            'status' => $payload['status'] ?? $payload['Status'] ?? null,
        ]);

        return [
            'status' => 200,
            'body' => [
                'status' => 'received',
                'event' => $eventType,
                'processed' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function acknowledgeUnhandledEvent(string $eventType, array $payload): array
    {
        Log::info('RoutePay webhook: unhandled event type.', [
            'event' => $eventType,
            'payload_keys' => array_keys($payload),
        ]);

        return [
            'status' => 200,
            'body' => [
                'status' => 'received',
                'event' => $eventType,
                'processed' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isPaymentSuccessful(array $payload): bool
    {
        $paymentStatusRaw = $payload['paymentStatus'] ?? null;
        $paymentStatus = strtolower(trim((string) ($paymentStatusRaw ?? '')));
        $status = strtolower(trim((string) ($payload['status'] ?? $payload['Status'] ?? '')));
        $responseCode = trim((string) ($payload['responseCode'] ?? ''));

        if (in_array($paymentStatus, ['successful', 'success', 'completed', 'approved'], true)) {
            return true;
        }

        if (in_array($status, ['successful', 'success', 'completed', 'approved'], true)) {
            return true;
        }

        if ($responseCode === '00') {
            return true;
        }

        if ($paymentStatus === '0' || $status === '0' || $paymentStatusRaw === 0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findQrRegistration(
        string $merchantReference,
        string $transactionReference,
        array $payload = [],
    ): ?RoutePayQrRegistration {
        if ($merchantReference !== '') {
            $registration = RoutePayQrRegistration::where('merchant_reference', $merchantReference)->first();
            if ($registration !== null) {
                return $registration;
            }
        }

        if ($transactionReference !== '') {
            $registration = RoutePayQrRegistration::where('transaction_reference', $transactionReference)->first();
            if ($registration !== null) {
                if ($merchantReference !== '' && $registration->merchant_reference !== $merchantReference) {
                    $registration->update(['merchant_reference' => $merchantReference]);
                }

                return $registration;
            }

            // QR creation ref often differs from settlement/payment ref (same payment, new id).
            $registration = $this->findQrRegistrationByTransactionPrefix($transactionReference);
            if ($registration !== null) {
                return $registration;
            }

            $registration = $this->findQrRegistrationByTransactionTimeWindow($transactionReference, $payload);
            if ($registration !== null) {
                return $registration;
            }
        }

        return $this->findQrRegistrationForSettlement($payload, $transactionReference);
    }

    /**
     * Settlement webhooks omit merchantReference; match recent registration by payment minute + amount.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function findQrRegistrationByTransactionTimeWindow(
        string $transactionReference,
        array $payload,
    ): ?RoutePayQrRegistration {
        if (strlen($transactionReference) < 10) {
            return null;
        }

        $settledAmount = (float) ($payload['settledAmount'] ?? $payload['transactionAmount'] ?? $payload['amount'] ?? 0);
        $settlementTime = $this->resolveSettlementEventTime($payload, $transactionReference);
        $prefixes = $this->transactionReferencePrefixes($transactionReference);

        $candidates = RoutePayQrRegistration::query()
            ->where(function ($q) use ($prefixes, $transactionReference) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('transaction_reference', 'like', $prefix.'%');
                }
                $q->orWhere('transaction_reference', $transactionReference);
            })
            ->whereNotNull('assessment_no')
            ->where('assessment_no', '!=', '')
            ->where('created_at', '>=', now()->subHours(72))
            ->orderByDesc('id')
            ->get();

        $best = $this->pickBestSettlementRegistrationCandidate(
            $candidates,
            $payload,
            $transactionReference,
            $settledAmount,
            $settlementTime,
            45
        );

        if ($best === null) {
            return null;
        }

        $storedRef = $best->transaction_reference;
        $best->update(['transaction_reference' => $transactionReference]);

        Log::info('RoutePay webhook: matched QR registration via transaction time window.', [
            'registration_id' => $best->id,
            'merchant_reference' => $best->merchant_reference,
            'assessment_no' => $best->assessment_no,
            'stored_transaction_reference' => $storedRef,
            'settlement_transaction_reference' => $transactionReference,
        ]);

        return $best->fresh();
    }

    /**
     * RoutePay settlement remarks often embed merchant id prefix: STLMNT/xjIw/...
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveMerchantIdFromSettlementPayload(array $payload): string
    {
        $remarks = trim((string) ($payload['remarks'] ?? ''));
        if ($remarks === '' || ! preg_match('#STLMNT/([^/]+)/#', $remarks, $matches)) {
            return '';
        }

        $fragment = trim($matches[1]);
        if ($fragment === '') {
            return '';
        }

        $agency = Lga::query()
            ->where('payment_gateway_client_id', $fragment)
            ->orWhere('payment_gateway_client_id', 'like', $fragment.'%')
            ->first();

        return trim((string) ($agency?->payment_gateway_client_id ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findQrRegistrationForSettlement(array $payload, string $transactionReference): ?RoutePayQrRegistration
    {
        $registration = $this->findQrRegistrationForSettlementScoped($payload, $transactionReference, true);
        if ($registration !== null) {
            return $registration;
        }

        return $this->findQrRegistrationForSettlementScoped($payload, $transactionReference, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function findQrRegistrationForSettlementScoped(
        array $payload,
        string $transactionReference,
        bool $scopeToAgency,
    ): ?RoutePayQrRegistration {
        $settledAmount = (float) ($payload['settledAmount'] ?? $payload['transactionAmount'] ?? $payload['amount'] ?? 0);
        if ($settledAmount <= 0) {
            return null;
        }

        $merchantId = trim((string) ($payload['merchantId'] ?? ''));
        if ($merchantId === '') {
            $merchantId = $this->resolveMerchantIdFromSettlementPayload($payload);
        }
        $agency = $this->resolveAgencyByMerchantId($merchantId);

        $settlementTime = $this->resolveSettlementEventTime($payload, $transactionReference);

        $candidates = RoutePayQrRegistration::query()
            ->when($scopeToAgency && $agency?->id, fn ($q) => $q->where('lga_id', $agency->id))
            ->whereNotNull('assessment_no')
            ->where('assessment_no', '!=', '')
            ->where('created_at', '>=', now()->subHours(72))
            ->orderByDesc('id')
            ->get();

        $registration = $this->pickBestSettlementRegistrationCandidate(
            $candidates,
            $payload,
            $transactionReference,
            $settledAmount,
            $settlementTime,
            30
        );

        if ($registration === null) {
            return null;
        }

        if ($transactionReference !== '') {
            $registration->update(['transaction_reference' => $transactionReference]);
        }

        Log::info('RoutePay webhook: matched QR registration via settlement heuristics.', [
            'registration_id' => $registration->id,
            'bill_id' => $registration->bill_id,
            'assessment_no' => $registration->assessment_no,
            'merchant_reference' => $registration->merchant_reference,
            'settled_amount' => $settledAmount,
            'amount_expected' => $registration->amount_expected,
            'transaction_reference' => $transactionReference,
            'scoped_to_agency' => $scopeToAgency,
            'settlement_time' => $settlementTime?->toIso8601String(),
        ]);

        return $registration->fresh();
    }

    protected function findQrRegistrationByTransactionPrefix(string $transactionReference): ?RoutePayQrRegistration
    {
        if (strlen($transactionReference) < 10) {
            return null;
        }

        foreach ($this->transactionReferencePrefixes($transactionReference) as $prefix) {
            $registration = RoutePayQrRegistration::query()
                ->where('transaction_reference', 'like', $prefix.'%')
                ->where('created_at', '>=', now()->subHours(72))
                ->orderByDesc('id')
                ->get()
                ->first(fn (RoutePayQrRegistration $reg) => ! $this->registrationAlreadySettled($reg, $transactionReference));

            if ($registration !== null) {
                return $registration;
            }
        }

        return null;
    }

    protected function isTransactionAlreadyAppliedToBill(string $transRef, ?int $billId): bool
    {
        if ($transRef === '') {
            return false;
        }

        if ($billId !== null && $billId > 0) {
            return BillPaymentLog::where('trans_ref', $transRef)
                ->where('bill_master', $billId)
                ->exists();
        }

        return BillPaymentLog::where('trans_ref', $transRef)
            ->where('bill_master', '>', 0)
            ->exists();
    }

    protected function registrationAlreadySettled(
        RoutePayQrRegistration $registration,
        string $incomingTransactionReference = '',
    ): bool {
        $refs = $this->registrationPaymentReferences($registration);
        if ($incomingTransactionReference !== '') {
            $refs[] = $incomingTransactionReference;
        }

        foreach (array_unique(array_filter($refs)) as $ref) {
            if ($this->isTransactionAlreadyAppliedToBill($ref, $registration->bill_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function registrationPaymentReferences(RoutePayQrRegistration $registration): array
    {
        return array_values(array_filter(array_unique([
            trim((string) $registration->transaction_reference),
            trim((string) $registration->merchant_reference),
        ])));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RoutePayQrRegistration>  $candidates
     */
    protected function pickBestSettlementRegistrationCandidate(
        $candidates,
        array $payload,
        string $transactionReference,
        float $settledAmount,
        ?Carbon $settlementTime,
        int $maxMinutesApart,
    ): ?RoutePayQrRegistration {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $hasTimingSignal = $settlementTime !== null
            || $this->parseRoutePayReferenceTime($transactionReference) !== null;

        foreach ($candidates as $registration) {
            if ($this->registrationAlreadySettled($registration, $transactionReference)) {
                continue;
            }

            if ($settledAmount > 0 && (float) ($registration->amount_expected ?? 0) > 0) {
                if (! $this->webhookAmountMatchesRegistration($settledAmount, (float) $registration->amount_expected, $payload)) {
                    continue;
                }
            }

            if (! $hasTimingSignal) {
                return $registration;
            }

            $distance = $this->registrationSettlementTimeDistance(
                $registration,
                $settlementTime,
                $transactionReference
            );

            if ($distance > ($maxMinutesApart * 60)) {
                continue;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $registration;
            }
        }

        return $best;
    }

    protected function registrationSettlementTimeDistance(
        RoutePayQrRegistration $registration,
        ?Carbon $settlementTime,
        string $settlementTransactionReference,
    ): int {
        $anchors = array_filter([
            $settlementTime,
            $this->parseRoutePayReferenceTime($settlementTransactionReference),
        ]);

        if ($anchors === []) {
            return PHP_INT_MAX;
        }

        $registrationTimes = array_filter([
            $registration->created_at instanceof Carbon ? $registration->created_at : null,
            $this->leraMerchantReferenceTimestamp((string) $registration->merchant_reference),
            $this->parseRoutePayReferenceTime((string) $registration->transaction_reference),
        ]);

        if ($registrationTimes === []) {
            return PHP_INT_MAX;
        }

        $minDistance = PHP_INT_MAX;
        foreach ($anchors as $anchor) {
            foreach ($registrationTimes as $registrationTime) {
                $minDistance = min($minDistance, abs($anchor->diffInSeconds($registrationTime, false)));
            }
        }

        return $minDistance;
    }

    /**
     * @return list<string>
     */
    protected function transactionReferencePrefixes(string $transactionReference): array
    {
        $prefixes = [];
        foreach ([12, 10, 8] as $length) {
            if (strlen($transactionReference) >= $length) {
                $prefixes[] = substr($transactionReference, 0, $length);
            }
        }

        return array_values(array_unique($prefixes));
    }

    protected function resolveSettlementEventTime(array $payload, string $transactionReference): ?Carbon
    {
        $raw = trim((string) ($payload['TransactionDateTime'] ?? $payload['transactionDateTime'] ?? ''));
        if ($raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
            }
        }

        return $this->parseRoutePayReferenceTime($transactionReference);
    }

    protected function parseRoutePayReferenceTime(string $reference): ?Carbon
    {
        $digits = preg_replace('/\D/', '', $reference) ?? '';
        if (strlen($digits) < 12) {
            return null;
        }

        if (strlen($digits) >= 14) {
            $parsed = Carbon::createFromFormat('YmdHis', substr($digits, 0, 14));
            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        $parsed = Carbon::createFromFormat('YmdHi', substr($digits, 0, 12));

        return $parsed instanceof Carbon ? $parsed : null;
    }

    protected function leraMerchantReferenceTimestamp(string $reference): ?Carbon
    {
        if (! RoutePayQrService::isLeraMerchantReference($reference)) {
            return null;
        }

        if (! preg_match('/^LERA-[a-f0-9]{13}-(\d{14})-\d{6}$/i', trim($reference), $matches)) {
            return null;
        }

        $parsed = Carbon::createFromFormat('YmdHis', $matches[1]);

        return $parsed instanceof Carbon ? $parsed : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function webhookAmountMatchesRegistration(float $webhookAmount, float $expected, array $payload): bool
    {
        if ($expected <= 0) {
            return false;
        }

        $fee = (float) ($payload['feeAmount'] ?? 0);
        if ($fee > 0 && abs($webhookAmount - ($expected + $fee)) < 0.05) {
            return true;
        }

        if (abs($webhookAmount - $expected) < 0.05) {
            return true;
        }

        // Surcharge included in settlement total (e.g. 100 bill + 1.50 fee = 101.50).
        if ($webhookAmount >= $expected && ($webhookAmount - $expected) <= max(10.0, $expected * 0.2)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isSettlementSuccessful(array $payload): bool
    {
        $status = strtolower(trim((string) ($payload['status'] ?? $payload['Status'] ?? '')));

        return in_array($status, ['success', 'successful', 'completed'], true);
    }

    /**
     * RoutePay sends { event, data: { merchantId, merchantReference, ... }, timestamp }.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeWebhookPayload(array $payload): array
    {
        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return $payload;
        }

        $nested = $payload['data'];
        unset($payload['data']);

        return array_merge($payload, $nested);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function merchantIdFromPayload(array $payload, Request $request): string
    {
        return (string) (
            $payload['merchantId']
            ?? $request->header('x-merchant-id')
            ?? ''
        );
    }

    protected function isTimestampWithinTolerance(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $messageTime = (int) $timestamp;
        $currentTime = time();
        $tolerance = max(60, (int) config('routepay.webhook_timestamp_tolerance', 300));

        return abs($currentTime - $messageTime) <= $tolerance;
    }
}
