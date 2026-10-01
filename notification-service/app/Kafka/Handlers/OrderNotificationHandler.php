<?php

namespace App\Kafka\Handlers;

use App\Events\RealtimeNotificationEvent;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class OrderNotificationHandler
{
    private const MAX_ATTEMPTS = 3;

    private const INITIAL_BACKOFF_SECONDS = 1;

    private const DLQ_TOPIC = 'order.notifications.dlq';

    public function __invoke(ConsumerMessage $message): void
    {
        $payload = $message->getBody();
        $event = $payload['event'] ?? 'unknown.order.event';
        $userId = $payload['user_id'] ?? null;
        $email = $payload['email'] ?? null;
        $data = $payload['data'] ?? [];

        // 1. Первичная фиксация в MongoDB (pending, attempts = 0)
        $log = NotificationLog::create([
            'event' => $event,
            'channel' => 'email',
            'user_id' => $userId,
            'recipient' => $email,
            'recipient_email' => $email,
            'payload' => $payload,
            'status' => NotificationLog::STATUS_PENDING,
            'attempts' => 0,
            'is_dlq' => false,
            'error_message' => null,
            'sent_at' => null,
        ]);

        $attempt = 0;
        $backoff = self::INITIAL_BACKOFF_SECONDS;
        $lastException = null;

        // 2. Retry с Exponential Backoff
        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            $log->increment('attempts');

            try {
                $this->dispatchScenario($event, $userId, $email, $data);

                // 3. Успех
                $log->update([
                    'status' => NotificationLog::STATUS_SENT,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return;

            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning("Order Kafka Consumer attempt {$attempt}/".self::MAX_ATTEMPTS." failed for event [{$event}]: {$e->getMessage()}");

                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($backoff);
                    $backoff *= 2;
                }
            }
        }

        // 4. Попытки исчерпаны: публикация в DLQ и статус failed
        $errorMessage = $lastException?->getMessage() ?? 'Max retry attempts exceeded for order event';

        Log::error("Order Kafka Consumer permanently failed for event [{$event}]. Routing to DLQ.", [
            'payload' => $payload,
            'attempts' => $attempt,
            'error' => $errorMessage,
        ]);

        $this->publishToDlq($payload, $errorMessage, $attempt);

        $log->update([
            'status' => NotificationLog::STATUS_FAILED,
            'is_dlq' => true,
            'error_message' => $errorMessage,
        ]);
    }

    private function dispatchScenario(string $event, mixed $userId, ?string $email, array $data): void
    {
        $orderId = $data['order_id'] ?? null;

        if (! $orderId) {
            throw new InvalidArgumentException("Missing 'order_id' in order payload data.");
        }

        $mailable = match ($event) {
            'order.paid' => new OrderPaidMail(
                orderId: $orderId,
                amount: $data['amount'] ?? 0,
                currency: $data['currency'] ?? 'RUB',
                items: $data['items'] ?? []
            ),
            'order.confirmed' => new OrderConfirmedMail(
                orderId: $orderId,
                estimatedDelivery: $data['estimated_delivery'] ?? null
            ),
            'order.cancelled' => new OrderCancelledMail(
                orderId: $orderId,
                reason: $data['reason'] ?? 'не указана'
            ),
            default => null,
        };

        if (! $mailable || ! $email) {
            throw new InvalidArgumentException("Unsupported order event [{$event}] or missing recipient email.");
        }

        Mail::to($email)->send($mailable);

        $title = match ($event) {
            'order.paid' => 'Заказ оплачен',
            'order.confirmed' => 'Заказ подтвержден',
            'order.cancelled' => 'Заказ отменен',
            default => 'Обновление заказа',
        };

        $messageText = match ($event) {
            'order.paid' => "Заказ #{$orderId} успешно оплачен.",
            'order.confirmed' => "Заказ #{$orderId} собирается на складе.",
            'order.cancelled' => "Заказ #{$orderId} отменен.",
            default => "Обновлен статус заказа #{$orderId}.",
        };

        if ($userId) {
            event(new RealtimeNotificationEvent(
                userId: $userId,
                type: $event,
                title: $title,
                message: $messageText,
                data: $data
            ));
        }
    }

    private function publishToDlq(array $payload, string $errorMessage, int $attempts): void
    {
        try {
            $dlqMessage = new Message(
                headers: [
                    'x-dlq-reason' => 'max-retries-exceeded',
                    'x-attempts' => (string) $attempts,
                    'x-failed-at' => now()->toIso8601String(),
                ],
                body: [
                    'original_payload' => $payload,
                    'error_message' => $errorMessage,
                ]
            );

            Kafka::publish()
                ->onTopic(self::DLQ_TOPIC)
                ->withMessage($dlqMessage)
                ->send();
        } catch (Throwable $e) {
            Log::critical("Failed to publish message to Order DLQ topic: {$e->getMessage()}", [
                'payload' => $payload,
            ]);
        }
    }
}
