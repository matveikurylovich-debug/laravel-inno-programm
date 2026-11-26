<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\NotificationLogRepository;
use App\Data\AuthNotificationData;
use App\Data\OrderCancelledData;
use App\Data\OrderConfirmedData;
use App\Data\OrderPaidData;
use App\Enums\AuthNotificationEvent;
use App\Enums\NotificationStatus;
use App\Enums\OrderNotificationEvent;
use App\Exceptions\NonRetryableException;
use App\Exceptions\NotificationReplayException;
use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationLogRepository $logs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $event, array $payload): void
    {
        $orderEvent = OrderNotificationEvent::tryFrom($event);
        if ($orderEvent !== null) {
            match ($orderEvent) {
                OrderNotificationEvent::Paid => $this->dispatchOrderPaid(OrderPaidData::fromPayload($payload)),
                OrderNotificationEvent::Confirmed => $this->dispatchOrderConfirmed(OrderConfirmedData::fromPayload($payload)),
                OrderNotificationEvent::Cancelled => $this->dispatchOrderCancelled(OrderCancelledData::fromPayload($payload)),
            };

            return;
        }

        $this->dispatchAuth(AuthNotificationData::fromPayload($payload, $event));
    }

    public function dispatchOrderPaid(OrderPaidData $data): void
    {
        Mail::to($data->email)->send(new OrderPaidMail(
            orderId: $data->orderId,
            amount: $data->amount,
            currency: $data->currency,
        ));
    }

    public function dispatchOrderConfirmed(OrderConfirmedData $data): void
    {
        Mail::to($data->email)->send(new OrderConfirmedMail(
            orderId: $data->orderId,
            estimatedDelivery: $data->estimatedDelivery,
            amount: $data->amount,
            currency: $data->currency,
        ));
    }

    public function dispatchOrderCancelled(OrderCancelledData $data): void
    {
        Mail::to($data->email)->send(new OrderCancelledMail(
            orderId: $data->orderId,
            reason: $data->reason,
            refundedAmount: $data->refundedAmount,
            currency: $data->currency,
        ));
    }

    public function dispatchAuth(AuthNotificationData $data): void
    {
        $mailable = match ($data->event) {
            AuthNotificationEvent::UserRegistered => new UserRegisteredMail(
                name: (string) $data->name,
                verificationUrl: $data->verificationUrl,
            ),
            AuthNotificationEvent::PasswordReset => new PasswordResetMail(
                resetUrl: (string) $data->resetUrl,
                expiresIn: (int) $data->expiresIn,
            ),
            AuthNotificationEvent::TwoFactorCode => new TwoFactorCodeMail(
                code: (string) $data->code,
                ttlMinutes: (int) $data->ttlMinutes,
            ),
        };

        Mail::to($data->email)->send($mailable);
    }

    public function replay(NotificationLog $log, int|string|null $actorId = null): NotificationLog
    {
        if ($log->status !== NotificationStatus::Failed) {
            throw NotificationReplayException::notFailed();
        }

        $payload = $this->resolvePayload($log);
        $rawEvent = $payload['event'] ?? $log->event;
        $event = is_string($rawEvent) && trim($rawEvent) !== '' ? trim($rawEvent) : $log->event;

        $authEvent = AuthNotificationEvent::tryFrom($event);
        if ($authEvent !== null && $authEvent->containsSecret()) {
            throw NotificationReplayException::secretRedacted();
        }

        if ($authEvent === null && OrderNotificationEvent::tryFrom($event) === null) {
            throw NotificationReplayException::unsupportedEvent($event);
        }

        if (! isset($payload['email']) && is_string($log->recipient) && $log->recipient !== '') {
            $payload['email'] = $log->recipient;
        }

        $log = $this->logs->incrementAttempts($log);

        try {
            $this->send($event, $payload);

            $updated = $this->logs->markReplayed($log, $actorId);

            Log::info('notification.replayed', [
                'notification_id' => (string) $log->getKey(),
                'actor_id' => $actorId,
            ]);

            return $updated;
        } catch (Throwable $e) {
            $this->logs->markFailed($log, $e->getMessage(), (bool) $log->is_dlq);

            Log::error('notification.replay_failed', [
                'notification_id' => (string) $log->getKey(),
                'actor_id' => $actorId,
                'exception' => $e,
            ]);

            throw NotificationReplayException::deliveryFailed($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(NotificationLog $log): array
    {
        $raw = $log->payload;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    return $decoded;
                }
            } catch (Throwable $e) {
                throw NotificationReplayException::deliveryFailed(
                    new NonRetryableException('Некорректный JSON в payload уведомления.', 0, $e),
                );
            }
        }

        return [];
    }
}
