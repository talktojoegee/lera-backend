<?php

namespace Tests\Unit;

use App\Models\RoutePayQrRegistration;
use App\Services\RoutePayWebhookService;
use Tests\TestCase;

class RoutePayWebhookServiceTest extends TestCase
{
    public function test_bill_payment_amount_uses_amount_field_without_subtracting_fee(): void
    {
        $service = new class extends RoutePayWebhookService
        {
            public function resolveAmountForTest(array $payload, ?RoutePayQrRegistration $registration = null): float
            {
                return $this->resolveBillPaymentAmount($payload, $registration);
            }
        };

        $registration = new RoutePayQrRegistration([
            'amount_expected' => 100.0,
        ]);

        $payload = [
            'amount' => 100,
            'totalAmount' => 101.5,
            'feeAmount' => 1.5,
        ];

        $this->assertSame(100.0, $service->resolveAmountForTest($payload, $registration));
    }

    public function test_bill_payment_amount_falls_back_to_expected_when_amount_is_missing(): void
    {
        $service = new class extends RoutePayWebhookService
        {
            public function resolveAmountForTest(array $payload, ?RoutePayQrRegistration $registration = null): float
            {
                return $this->resolveBillPaymentAmount($payload, $registration);
            }
        };

        $registration = new RoutePayQrRegistration([
            'amount_expected' => 100.0,
        ]);

        $payload = [
            'totalAmount' => 101.5,
            'feeAmount' => 1.5,
        ];

        $this->assertSame(100.0, $service->resolveAmountForTest($payload, $registration));
    }
}
