<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Contracts\NotificationLogRepository;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Exceptions\NonRetryableException;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\EventPayload;
use App\Support\SensitivePayload;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\Handler;
use Junges\Kafka\Contracts\MessageConsumer;
use Throwable;

abstract class AbstractNotificationConsumer implements Handler
{
    public function __construct(
        protected readonly NotificationDispatcher $notifications,
        protected readonly NotificationLogRepository $logs,
    ) {}

    public function __invoke(ConsumerMessage $message, ?MessageConsumer $consumer = null): void
    {
        $rawBody = $message->getBody();
        if (! is_array($rawBody) || $rawBody === []) {
            throw new NonRetryableException('Тело сообщения Kafka должно быть непустым массивом.');
        }

        /** @var array<string, mixed> $rawBody */
        $payload = EventEnvelope::flatten($rawBody);

        $rawEvent = $payload['event'] ?? ($message->getHeaders()['event-type'] ?? null);
        if (! is_string($rawEvent) || trim($rawEvent) === '') {
            throw new NonRetryableException('Тип события Kafka не указан.');
        }

        $event = trim($rawEvent);
        if (! $this->shouldProcess($event)) {
            $consumer?->commit($message);

            return;
        }

        $attributes = EventPayload::attributes($payload);
        $eventId = EventPayload::optionalEventId($attributes);

        $existing = $eventId !== null ? $this->logs->findByEventId($eventId) : null;
        if ($existing instanceof NotificationLog && $existing->status === NotificationStatus::Sent) {
            Log::info('notification.deduplicated', [
                'event_id' => $eventId,
                'event' => $event,
            ]);
            $consumer?->commit($message);

            return;
        }

        $redactedPayload = SensitivePayload::redact($payload);
        $recipient = is_string($attributes['email'] ?? null) && trim($attributes['email']) !== ''
            ? trim($attributes['email'])
            : null;
        $rawUserId = $attributes['user_id'] ?? null;
        $userId = is_int($rawUserId) || (is_string($rawUserId) && trim($rawUserId) !== '')
            ? $rawUserId
            : null;

        $log = $existing ?? $this->createOrLoadPendingLog(
            eventId: $eventId,
            userId: $userId,
            event: $event,
            recipient: $recipient,
            redactedPayload: $redactedPayload,
        );

        if ($log->status === NotificationStatus::Sent) {
            $consumer?->commit($message);

            return;
        }

        $log = $this->logs->incrementAttempts($log);

        try {
            $this->notifications->send($event, $payload);

            $this->logs->markSent($log);
            $consumer?->commit($message);
        } catch (NonRetryableMessageException $e) {
            $this->logs->markFailed($log, $e->getMessage(), true);

            Log::warning('notification.non_retryable', [
                'event' => $event,
                'event_id' => $eventId,
                'exception' => $e,
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->logs->markFailed($log, $e->getMessage(), false);

            Log::error('notification.delivery_failed', [
                'event' => $event,
                'event_id' => $eventId,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    abstract protected function shouldProcess(string $event): bool;

    /**
     * @param  array<string, mixed>  $redactedPayload
     */
    private function createOrLoadPendingLog(
        ?string $eventId,
        int|string|null $userId,
        string $event,
        ?string $recipient,
        array $redactedPayload,
    ): NotificationLog {
        try {
            return $this->logs->createPending([
                'event_id' => $eventId,
                'user_id' => $userId,
                'event' => $event,
                'channel' => NotificationChannel::Email,
                'recipient' => $recipient,
                'payload' => $redactedPayload,
            ]);
        } catch (Throwable $e) {
            if ($eventId !== null && ($e->getCode() === 11000 || str_contains($e->getMessage(), 'E11000'))) {
                $concurrent = $this->logs->findByEventId($eventId);
                if ($concurrent instanceof NotificationLog) {
                    return $concurrent;
                }
            }

            throw $e;
        }
    }
}
