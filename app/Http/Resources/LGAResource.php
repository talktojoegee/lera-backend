<?php

namespace App\Http\Resources;

use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LGAResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lgaName' => $this->lga_name,
            'paymentGatewayConfigured' => $this->hasPaymentGatewayConfiguration(),
            'paymentGatewayKeyConfigured' => $this->resolvePaymentGatewaySecretKey() !== null,
            'paymentGatewaySecretKeyMasked' => $this->maskedPaymentGatewaySecretKey(),
            'paymentGatewayClientId' => $this->payment_gateway_client_id,
            'paymentGatewayAuthUrl' => $this->payment_gateway_auth_url,
            'paymentGatewayBaseUrl' => $this->payment_gateway_base_url,
            'paymentGatewayQrGenerateUrl' => $this->payment_gateway_qr_generate_url,
            'paymentGatewayQrReturnUrl' => $this->payment_gateway_qr_return_url
                ?: Lga::routePayCheckoutReturnUrl(null),
            'paymentGatewayQrAlternateBaseUrls' => $this->payment_gateway_qr_alternate_base_urls,
            'paymentGatewayQrDurationSeconds' => $this->payment_gateway_qr_duration_seconds
                ?? Lga::DEFAULT_QR_DURATION_SECONDS,
            'paymentGatewayWebhookUrl' => Lga::defaultWebhookUrl(),
            'paymentGatewayQrVerifyMe' => $this->payment_gateway_qr_verify_me ?? Lga::DEFAULT_QR_VERIFY_ME,
            /** Merchant logo for QR is platform-wide (ROUTEPAY_MERCHANT_LOGO_PATH), not per agency. */
            'paymentGatewayUsesGlobalLogo' => true,
        ];
    }
}
