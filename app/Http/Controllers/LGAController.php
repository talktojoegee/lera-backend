<?php

namespace App\Http\Controllers;

use App\Http\Resources\LGAResource;
use App\Models\Lga;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LGAController extends Controller
{
    public function __construct() {}

    public function createLGA(Request $request)
    {
        if ($request->filled('id')) {
            return $this->updateLGA($request);
        }

        $validator = $this->makeLgaValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        Lga::create($this->mapRequestToLgaAttributes($request, true, true));

        return response()->json(['message' => 'Success! LGA added.'], 201);
    }

    public function updateLGA(Request $request)
    {
        $validator = $this->makeLgaValidator($request, true);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        $lga = Lga::find($request->id);
        if (empty($lga)) {
            return response()->json([
                'message' => 'No record found.',
            ], 404);
        }

        if (
            empty($request->paymentGatewaySecretKey)
            && $lga->resolvePaymentGatewaySecretKey() === null
        ) {
            return response()->json([
                'errors' => [
                    'paymentGatewaySecretKey' => [
                        'Payment gateway secret key is required.',
                    ],
                ],
            ], 422);
        }

        $updateData = $this->mapRequestToLgaAttributes($request, false, false);
        if (! empty($request->paymentGatewaySecretKey)) {
            $updateData['payment_gateway_secret_key'] = $request->paymentGatewaySecretKey;
        }

        $lga->update($updateData);

        return response()->json(['message' => 'Success! Agency updated.'], 200);
    }

    public function showAllLGAs()
    {
        return LGAResource::collection(Lga::fetchAllLGAs());
    }

    public function showDistributionCode()
    {
        $record = PropertyList::whereNotNull('distribution_code')
            ->select('distribution_code')
            ->distinct()
            ->pluck('distribution_code');

        return response()->json(['data' => $record], 200);
    }

    protected function makeLgaValidator(Request $request, bool $isUpdate)
    {
        $secretRule = $isUpdate ? 'nullable|string|min:8' : 'required|string|min:8';

        return Validator::make($request->all(), [
            'id' => $isUpdate ? 'required|exists:lgas,id' : 'nullable',
            'lgaName' => 'required|unique:lgas,lga_name'.($isUpdate ? ','.$request->id : ''),
            'paymentGatewayClientId' => 'required|string|max:255',
            'paymentGatewaySecretKey' => $secretRule,
            'paymentGatewayAuthUrl' => 'nullable|url|max:500',
            'paymentGatewayBaseUrl' => 'nullable|url|max:500',
            'paymentGatewayQrGenerateUrl' => 'nullable|url|max:500',
            'paymentGatewayQrReturnUrl' => 'nullable|url|max:500',
            'paymentGatewayQrAlternateBaseUrls' => 'nullable|string|max:2000',
            'paymentGatewayQrVerifyMe' => 'nullable|boolean',
            'paymentGatewayQrDurationSeconds' => 'required|integer|min:60|max:31536000',
        ], [
            'lgaName.required' => 'Agency name is required.',
            'lgaName.unique' => 'An agency with this name already exists.',
            'paymentGatewayClientId.required' => 'Payment gateway client ID is required.',
            'paymentGatewaySecretKey.required' => 'Payment gateway secret key is required.',
            'paymentGatewaySecretKey.min' => 'Payment gateway secret key must be at least 8 characters.',
            'paymentGatewayAuthUrl.url' => 'RoutePay auth URL must be a valid URL.',
            'paymentGatewayBaseUrl.url' => 'RoutePay API base URL must be a valid URL.',
            'paymentGatewayQrDurationSeconds.required' => 'QR duration (seconds) is required.',
            'paymentGatewayQrDurationSeconds.min' => 'QR duration must be at least 60 seconds.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapRequestToLgaAttributes(Request $request, bool $includeSecret = true, bool $isCreate = true): array
    {
        $attributes = [
            'lga_name' => $request->lgaName,
            'payment_gateway_client_id' => $request->paymentGatewayClientId,
            'payment_gateway_auth_url' => rtrim(trim((string) ($request->paymentGatewayAuthUrl ?? Lga::DEFAULT_AUTH_URL)), '/'),
            'payment_gateway_base_url' => rtrim(trim((string) ($request->paymentGatewayBaseUrl ?? Lga::DEFAULT_BASE_URL)), '/'),
            'payment_gateway_qr_generate_url' => $request->filled('paymentGatewayQrGenerateUrl')
                ? rtrim(trim((string) $request->paymentGatewayQrGenerateUrl), '/')
                : null,
            'payment_gateway_qr_return_url' => $request->filled('paymentGatewayQrReturnUrl')
                ? trim((string) $request->paymentGatewayQrReturnUrl)
                : (config('app.frontend_url') ?: config('routepay.qr_return_url') ?: null),
            'payment_gateway_qr_duration_seconds' => (int) $request->paymentGatewayQrDurationSeconds,
            'payment_gateway_qr_verify_me' => $request->has('paymentGatewayQrVerifyMe')
                ? filter_var($request->paymentGatewayQrVerifyMe, FILTER_VALIDATE_BOOLEAN)
                : Lga::DEFAULT_QR_VERIFY_ME,
            'payment_gateway_qr_alternate_base_urls' => $request->filled('paymentGatewayQrAlternateBaseUrls')
                ? trim((string) $request->paymentGatewayQrAlternateBaseUrls)
                : null,
        ];

        if ($includeSecret && ! empty($request->paymentGatewaySecretKey)) {
            $attributes['payment_gateway_secret_key'] = $request->paymentGatewaySecretKey;
        }

        return $attributes;
    }
}
