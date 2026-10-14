<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Обработка входящих событий от Stripe.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Получаем «сырое» тело запроса (raw body)
        $payload = $request->getContent();

        // 2. Достаем сигнатуру из заголовка Stripe-Signature
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = (string) config('services.stripe.webhook_secret');

        // Базовая валидация наличия заголовка и секрета
        if (! $sigHeader || $webhookSecret === '') {
            Log::error('Stripe Webhook: отсутствует сигнатура или STRIPE_WEBHOOK_SECRET');

            return response()->json(['error' => 'Webhook configuration error'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Верификация подписи
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException $e) {
            // Тело запроса повреждено или не является валидным JSON
            Log::warning("Stripe Webhook: невалидный payload: {$e->getMessage()}");

            return response()->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            // Сигнатура не совпала (поддельный запрос или чужой секрет)
            Log::warning("Stripe Webhook: ошибка проверки сигнатуры: {$e->getMessage()}");

            return response()->json(['error' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        }

        Log::info("Stripe Webhook получен: [{$event->type}], ID: {$event->id}");

        // 4. Диспетчеризация событий саги
        $stripeObject = $event->data->object->toArray();

        match ($event->type) {
            'payment_intent.succeeded' => $this->orderService->handlePaymentSucceeded($stripeObject),
            'payment_intent.payment_failed' => $this->orderService->handlePaymentFailed($stripeObject),
            default => Log::info("Stripe Webhook пропущен (не обрабатывается сагой): {$event->type}"),
        };

        // 5. Обязательный ответ 200 OK для Stripe
        return response()->json(['status' => 'success'], Response::HTTP_OK);
    }
}
