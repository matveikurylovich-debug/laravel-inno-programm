<?php

namespace App\Kafka\Handlers;

use App\Events\RealtimeNotificationEvent;
use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\ProfileUpdateConfirmMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class AuthNotificationHandler
{
    private const MAX_ATTEMPTS = 3;

    private const INITIAL_BACKOFF_SECONDS = 1;

    private const DLQ_TOPIC = 'auth.notifications.dlq';

    public function __invoke(ConsumerMessage $message): void
    {
        // 1. Извлечение payload из ConsumerMessage
        $payload = $message->getBody();
        $event = $payload['event'] ?? 'UnknownEvent';
        $userId = $payload['user_id'] ?? null;
        $email = $payload['email'] ?? null;
        $data = $payload['data'] ?? [];

        // 2. Первичный аудит в MongoDB со статусом pending и attempts = 0
        $log = NotificationLog::create([
            'event' => $event,
            'channel' => 'email',
            'user_id' => $userId,
            'recipient' => $email,
            'recipient_email' => $email,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'is_dlq' => false,
            'error_message' => null,
            'sent_at' => null,
        ]);

        $attempt = 0;
        $backoff = self::INITIAL_BACKOFF_SECONDS;
        $lastException = null;

        // 3. Цикл повторных попыток с экспоненциальным backoff (1s -> 2s)
        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            $log->increment('attempts');

            try {
                // Маршрутизация сценариев (Mailable + Pusher Event)
                $this->dispatchScenario($event, $userId, $email, $data);

                // 4. Успех: фиксация статуса sent
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return;

            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning("Kafka Consumer attempt {$attempt}/".self::MAX_ATTEMPTS." failed for event [{$event}]: {$e->getMessage()}");

                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($backoff);
                    $backoff *= 2;
                }
            }
        }

        // 5. Попытки исчерпаны: перевод в failed и отправка в топик DLQ
        $errorMessage = $lastException?->getMessage() ?? 'Max retry attempts exceeded';

        Log::error("Kafka Consumer permanently failed for event [{$event}]. Routing to DLQ.", [
            'payload' => $payload,
            'attempts' => $attempt,
            'error' => $errorMessage,
        ]);

        $this->publishToDlq($payload, $errorMessage, $attempt);

        $log->update([
            'status' => 'failed',
            'is_dlq' => true,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Маршрутизация сценариев через match-выражение
     */
    private function dispatchScenario(string $event, mixed $userId, ?string $email, array $data): void
    {
        // 1. Сборка нужного Mailable под конструкторы
        $mailable = match ($event) {
            'user.registered' => new UserRegisteredMail(
                name: $data['name'] ?? 'Пользователь'
            ),
            'auth.password_reset' => new PasswordResetMail(
                resetUrl: $data['reset_url'] ?? '#',
                expiresIn: (int) ($data['expires_in'] ?? 15)
            ),
            'auth.two_factor_code' => new TwoFactorCodeMail(
                code: (string) ($data['code'] ?? '000000'),
                ttlMinutes: (int) ($data['ttl_minutes'] ?? 5)
            ),
            'auth.profile_update_confirm' => new ProfileUpdateConfirmMail(
                code: (string) ($data['code'] ?? '000000'),
                field: $data['field'] ?? 'профиль'
            ),
            default => null,
        };

        if ($mailable === null || empty($email)) {
            throw new InvalidArgumentException('Unsupported event or missing recipient email');
        }

        Mail::to($email)->send($mailable);

        // 3. Заголовки и тексты для Pusher
        $title = $data['title'] ?? match ($event) {
            'user.registered' => 'Регистрация',
            'auth.password_reset' => 'Сброс пароля',
            'auth.two_factor_code' => 'Код безопасности',
            'auth.profile_update_confirm' => 'Обновление профиля',
            default => 'Уведомление',
        };

        $message = $data['message'] ?? match ($event) {
            'user.registered' => 'Добро пожаловать в сервис!',
            'auth.password_reset' => 'Ссылка для сброса пароля отправлена на почту.',
            'auth.two_factor_code' => 'Код подтверждения отправлен на почту.',
            'auth.profile_update_confirm' => 'Подтвердите изменение профиля кодом.',
            default => "Событие {$event} успешно обработано.",
        };

        // 4. Отправка Realtime события в Pusher
        if ($userId) {
            event(new RealtimeNotificationEvent(
                userId: $userId,
                type: $event,
                title: $title,
                message: $message,
                data: $data
            ));
        }
    }

    /**
     * Публикация сообщения в Dead Letter Queue (Kafka)
     */
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
            Log::critical("Critical: Failed to publish message to DLQ topic: {$e->getMessage()}", [
                'payload' => $payload,
            ]);
        }
    }
}
