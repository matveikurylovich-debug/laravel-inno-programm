<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use Illuminate\Support\Facades\Artisan;

it('purges stored secrets via notification:redact-secrets and notifications:purge-secrets alias', function (): void {
    $first = seedNotificationLog([
        'event' => 'auth.password_reset',
        'status' => NotificationStatus::Sent,
        'recipient' => 'ann@example.com',
        'payload' => [
            'event' => 'auth.password_reset',
            'email' => 'ann@example.com',
            'data' => [
                'reset_url' => 'http://localhost:3000/reset-password/token',
                'code' => '123456',
                'expires_in' => 60,
            ],
        ],
    ]);

    expect(Artisan::call('notifications:purge-secrets'))->toBe(0);

    $fresh = findNotificationLog((string) $first->getKey());
    $payload = is_array($fresh->payload) ? $fresh->payload : [];
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

    expect($data)->not->toHaveKey('code')
        ->and($data)->not->toHaveKey('reset_url')
        ->and($data['expires_in'] ?? null)->toBe(60);
});

it('converts legacy JSON string payloads to decoded BSON arrays and prunes expired logs', function (): void {
    $legacy = seedNotificationLog([
        'event' => 'user.registered',
        'status' => NotificationStatus::Sent,
        'recipient' => 'legacy@example.com',
        'payload' => json_encode([
            'event' => 'user.registered',
            'email' => 'legacy@example.com',
            'data' => [
                'name' => 'Legacy',
                'code' => '654321',
            ],
        ], JSON_THROW_ON_ERROR),
        'created_at' => now()->subDays(120),
    ]);

    $recent = seedNotificationLog([
        'event' => 'order.paid',
        'status' => NotificationStatus::Sent,
        'recipient' => 'recent@example.com',
        'payload' => ['event' => 'order.paid'],
        'created_at' => now()->subDays(5),
    ]);

    expect(Artisan::call('notifications:convert-payloads'))->toBe(0);

    $converted = findNotificationLog((string) $legacy->getKey());
    expect($converted->payload)->toBeArray();
    $payload = is_array($converted->payload) ? $converted->payload : [];
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    expect($data['name'] ?? null)->toBe('Legacy')
        ->and($data)->not->toHaveKey('code');

    expect(Artisan::call('notification:prune-logs', ['--days' => '30']))->toBe(0);

    $remaining = allNotificationLogs();
    expect($remaining)->toHaveCount(1)
        ->and((string) $remaining[0]->getKey())->toBe((string) $recent->getKey());
});
