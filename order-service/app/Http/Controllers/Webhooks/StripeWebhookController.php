<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\PaymentGateway;
use App\Exceptions\InvalidWebhookPayload;
use App\Exceptions\InvalidWebhookSignature;
use App\Http\Controllers\Controller;
use App\Services\Payments\HandlePaymentFailed;
use App\Services\Payments\HandlePaymentSucceeded;
use App\Services\Payments\RefundPayment;
use Ecommerce\PlatformKit\Laravel\ProblemJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly HandlePaymentSucceeded $paymentSucceeded,
        private readonly HandlePaymentFailed $paymentFailed,
        private readonly RefundPayment $refundPayment,
        private readonly PaymentGateway $payments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $rawSecret = config('services.stripe.webhook_secret');
        $webhookSecret = is_string($rawSecret) ? $rawSecret : '';

        if (! is_string($sigHeader) || $sigHeader === '' || $webhookSecret === '') {
            Log::error('Stripe Webhook: отсутствует сигнатура или STRIPE_WEBHOOK_SECRET');

            return ProblemJson::make(Response::HTTP_BAD_REQUEST, 'Webhook configuration error');
        }

        try {
            $event = $this->payments->constructEvent($payload, $sigHeader);
        } catch (InvalidWebhookPayload $e) {
            Log::warning('Stripe Webhook: невалидный payload', ['exception' => $e]);

            return ProblemJson::make(Response::HTTP_BAD_REQUEST, 'Invalid payload');
        } catch (InvalidWebhookSignature $e) {
            Log::warning('Stripe Webhook: ошибка проверки сигнатуры', ['exception' => $e]);

            return ProblemJson::make(Response::HTTP_BAD_REQUEST, 'Invalid signature');
        }

        $inserted = DB::table('stripe_events')->insertOrIgnore([
            'event_id' => $event['id'],
            'type' => $event['type'],
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['status' => 'duplicate'], Response::HTTP_OK);
        }

        $object = $event['object'];
        $objectType = is_string($object['object'] ?? null) ? $object['object'] : null;

        match ($event['type']) {
            'payment_intent.succeeded' => $objectType === 'payment_intent'
                ? $this->paymentSucceeded->handle($object)
                : null,
            'payment_intent.payment_failed' => $objectType === 'payment_intent'
                ? $this->paymentFailed->handle($object)
                : null,
            'refund.created', 'refund.updated', 'refund.failed' => $objectType === 'refund'
                ? $this->refundPayment->handle($object)
                : null,
            default => Log::info('Stripe Webhook пропущен (не обрабатывается сагой)', [
                'event_type' => $event['type'],
            ]),
        };

        return response()->json(['status' => 'success'], Response::HTTP_OK);
    }
}
