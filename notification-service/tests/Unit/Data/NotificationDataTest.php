<?php

declare(strict_types=1);

use App\Data\AuthNotificationData;
use App\Data\OrderCancelledData;
use App\Data\OrderConfirmedData;
use App\Data\OrderPaidData;
use App\Enums\AuthNotificationEvent;
use App\Exceptions\NonRetryableException;
use App\Models\NotificationLog;
use App\Support\SensitivePayload;

it('parses valid OrderPaidData and rejects invalid contract fields', function (): void {
    $dto = OrderPaidData::fromPayload([
        'event_id' => 'evt-1',
        'user_id' => 4,
        'email' => 'buyer@example.com',
        'order_id' => 'ord-42',
        'total_amount' => '1250.50',
        'currency' => 'byn',
    ]);

    expect($dto->orderId)->toBe('ord-42')
        ->and($dto->amount)->toBe(1250.5)
        ->and($dto->currency)->toBe('BYN')
        ->and($dto->email)->toBe('buyer@example.com')
        ->and($dto->userId)->toBe(4)
        ->and($dto->eventId)->toBe('evt-1');

    expect(static fn (): OrderPaidData => OrderPaidData::fromPayload([
        'email' => 'buyer@example.com',
        'total_amount' => '100.00',
        'currency' => 'BYN',
    ]))->toThrow(NonRetryableException::class);

    expect(static fn (): OrderPaidData => OrderPaidData::fromPayload([
        'email' => 'buyer@example.com',
        'order_id' => 10,
        'currency' => 'BYN',
    ]))->toThrow(NonRetryableException::class);

    expect(static fn (): OrderPaidData => OrderPaidData::fromPayload([
        'email' => 'buyer@example.com',
        'order_id' => 10,
        'amount' => 50,
        'currency' => 'INVALID',
    ]))->toThrow(NonRetryableException::class);
});

it('validates OrderConfirmedData and OrderCancelledData without silent fallbacks', function (): void {
    $confirmed = OrderConfirmedData::fromPayload([
        'email' => 'buyer@example.com',
        'user_id' => 9,
        'data' => [
            'order_id' => 77,
            'estimated_delivery' => '2026-12-01',
            'total_amount' => '300.00',
            'currency' => 'RUB',
        ],
    ]);

    expect($confirmed->orderId)->toBe(77)
        ->and($confirmed->estimatedDelivery)->toBe('2026-12-01')
        ->and($confirmed->amount)->toBe(300.0)
        ->and($confirmed->currency)->toBe('RUB');

    expect(static fn (): OrderCancelledData => OrderCancelledData::fromPayload([
        'email' => 'buyer@example.com',
        'order_id' => 77,
    ]))->toThrow(NonRetryableException::class);

    $cancelled = OrderCancelledData::fromPayload([
        'email' => 'buyer@example.com',
        'order_id' => 'uuid-1',
        'reason' => 'cancelled_by_user',
        'refunded_amount' => '120.00',
        'currency' => 'BYN',
    ]);

    expect($cancelled->reason)->toBe('cancelled_by_user')
        ->and($cancelled->refundedAmount)->toBe(120.0)
        ->and($cancelled->currency)->toBe('BYN');
});

it('strictly validates AuthNotificationData and rejects deprecated or incomplete events', function (): void {
    $registered = AuthNotificationData::fromPayload([
        'event' => 'user.registered',
        'email' => 'user@example.com',
        'user_id' => 12,
        'data' => ['name' => 'Иван'],
    ]);

    expect($registered->event)->toBe(AuthNotificationEvent::UserRegistered)
        ->and($registered->name)->toBe('Иван');

    expect(static fn (): AuthNotificationData => AuthNotificationData::fromPayload([
        'event' => 'user.registered',
        'email' => 'user@example.com',
        'data' => [],
    ]))->toThrow(NonRetryableException::class);

    expect(static fn (): AuthNotificationData => AuthNotificationData::fromPayload([
        'event' => 'auth.password_reset',
        'email' => 'user@example.com',
        'data' => ['reset_url' => 'not-a-url'],
    ]))->toThrow(NonRetryableException::class);

    expect(static fn (): AuthNotificationData => AuthNotificationData::fromPayload([
        'event' => 'auth.two_factor_code',
        'email' => 'user@example.com',
        'data' => ['code' => '123456'],
    ]))->toThrow(NonRetryableException::class);

    expect(static fn (): AuthNotificationData => AuthNotificationData::fromPayload([
        'event' => 'auth.profile_update_confirm',
        'email' => 'user@example.com',
        'data' => ['code' => '123456'],
    ]))->toThrow(NonRetryableException::class);
});

it('redacts sensitive secrets recursively and configures NotificationLog for native BSON', function (): void {
    $redacted = SensitivePayload::redact([
        'code' => '111111',
        'reset_url' => 'https://example.com/reset',
        'data' => [
            'code' => '222222',
            'reset_url' => 'https://example.com/reset-nested',
            'ttl_minutes' => 5,
        ],
    ]);

    expect($redacted)->not->toHaveKey('code')
        ->and($redacted)->not->toHaveKey('reset_url')
        ->and($redacted['data'])->toBe(['ttl_minutes' => 5]);

    $model = new NotificationLog;
    expect($model->getTable())->toBe('notification_logs')
        ->and($model->getFillable())->toContain('event_id', 'user_id', 'replayed_by', 'replayed_at')
        ->and($model->getFillable())->not->toContain('recipient_email')
        ->and($model->getCasts())->not->toHaveKey('payload');
});
