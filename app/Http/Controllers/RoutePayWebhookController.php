<?php

namespace App\Http\Controllers;

use App\Services\RoutePayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutePayWebhookController extends Controller
{
    public function __construct(
        protected RoutePayWebhookService $webhookService,
    ) {}

    /**
     * RoutePay webhook receiver.
     *
     * Configure in RoutePay merchant portal:
     * {APP_URL}/api/routepay/webhook
     *
     * Events are sent as query string, e.g. ?event=payment
     *
     * @see https://developer.routepay.com/?view=overview#webhook-url-configuration
     */
    public function handle(Request $request): JsonResponse
    {
        $result = $this->webhookService->handleWebhook($request);

        return response()->json($result['body'], $result['status']);
    }
}
