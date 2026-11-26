<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use App\Mail\Auth\UserRegisteredMail;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('allows admin and analyst to list notifications with filters and exposes user_id and event_id', function (): void {
    seedNotificationLog([
        'event_id' => 'evt-ann-1',
        'user_id' => 101,
        'event' => 'user.registered',
        'status' => NotificationStatus::Sent,
        'recipient' => 'ann@example.com',
        'created_at' => now(),
    ]);

    seedNotificationLog([
        'event_id' => 'evt-bob-1',
        'user_id' => 202,
        'event' => 'order.paid',
        'status' => NotificationStatus::Failed,
        'recipient' => 'bob@example.com',
        'created_at' => now()->subDays(3),
        'error_message' => 'smtp down',
    ]);

    $old = seedNotificationLog([
        'event_id' => 'evt-ann-old',
        'user_id' => 101,
        'event' => 'order.cancelled',
        'status' => NotificationStatus::Sent,
        'recipient' => 'ann@example.com',
        'created_at' => now()->subDays(20),
    ]);

    getJson('/api/v1/notifications?status=sent&recipient=ann', roleHeaders('admin'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'recipient' => 'ann@example.com',
            'status' => 'sent',
            'user_id' => 101,
        ]);

    getJson('/api/v1/notifications?status=failed&channel=email', roleHeaders('analyst'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'event_id' => 'evt-bob-1',
            'recipient' => 'bob@example.com',
            'status' => 'failed',
            'user_id' => 202,
        ]);

    $recent = getJson('/api/v1/notifications?'.http_build_query([
        'date_from' => now()->subDays(5)->toDateString(),
        'date_to' => now()->toDateString(),
        'channel' => 'email',
    ]), roleHeaders('admin'))->assertOk();

    /** @var list<array<string, mixed>> $recentData */
    $recentData = $recent->json('data');
    $ids = collect($recentData)->pluck('id');

    expect($ids->contains((string) $old->getKey()))->toBeFalse()
        ->and($ids)->toHaveCount(2);
});

it('validates index query parameters strictly and returns 422 on per_page=101 or invalid date range', function (): void {
    getJson('/api/v1/notifications?per_page=101', roleHeaders('admin'))
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('errors.per_page.0', static fn (string $msg): bool => $msg !== '');

    getJson('/api/v1/notifications?status=unknown', roleHeaders('admin'))
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json');

    getJson('/api/v1/notifications?date_from=2026-11-25&date_to=2026-11-20', roleHeaders('admin'))
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json');
});

it('forbids customer access to notifications and forbids analyst from replaying', function (): void {
    $log = seedNotificationLog([
        'event' => 'user.registered',
        'status' => NotificationStatus::Failed,
        'recipient' => 'ann@example.com',
        'payload' => ['event' => 'user.registered', 'email' => 'ann@example.com', 'data' => ['name' => 'Ann']],
    ]);

    $id = (string) $log->getKey();

    getJson('/api/v1/notifications', roleHeaders('customer'))->assertForbidden();
    getJson('/api/v1/notifications/'.$id, roleHeaders('customer'))->assertForbidden();
    postJson('/api/v1/notifications/'.$id.'/replay', [], roleHeaders('customer'))->assertForbidden();
    postJson('/api/v1/notifications/'.$id.'/replay', [], roleHeaders('analyst'))->assertForbidden();
});

it('returns notification details without payload secrets and 404 for missing id', function (): void {
    $log = seedNotificationLog([
        'event_id' => 'evt-sec-1',
        'user_id' => 9,
        'event' => 'auth.two_factor_code',
        'status' => NotificationStatus::Sent,
        'recipient' => 'ann@example.com',
        'payload' => [
            'event' => 'auth.two_factor_code',
            'email' => 'ann@example.com',
            'data' => [
                'code' => '123456',
                'reset_url' => 'http://localhost:3000/reset-password/token',
                'ttl_minutes' => 5,
            ],
        ],
    ]);

    $response = getJson('/api/v1/notifications/'.$log->getKey(), roleHeaders('analyst'))
        ->assertOk()
        ->assertJsonPath('data.event', 'auth.two_factor_code')
        ->assertJsonPath('data.user_id', 9)
        ->assertJsonPath('data.event_id', 'evt-sec-1')
        ->assertJsonMissingPath('data.payload');

    $body = (string) $response->getContent();
    expect(str_contains($body, '123456'))->toBeFalse()
        ->and(str_contains($body, 'reset-password/token'))->toBeFalse();

    getJson('/api/v1/notifications/65f999999999999999999999', roleHeaders('admin'))
        ->assertNotFound();
});

it('rejects replay of two_factor_code and password_reset with domain 422 problem code', function (): void {
    Mail::fake();

    $twoFactor = seedNotificationLog([
        'event' => 'auth.two_factor_code',
        'status' => NotificationStatus::Failed,
        'recipient' => 'ann@example.com',
        'payload' => ['event' => 'auth.two_factor_code', 'email' => 'ann@example.com', 'data' => ['ttl_minutes' => 5]],
        'is_dlq' => true,
    ]);

    postJson('/api/v1/notifications/'.$twoFactor->getKey().'/replay', [], roleHeaders('admin'))
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('code', 'notification_secret_redacted');

    $reset = seedNotificationLog([
        'event' => 'auth.password_reset',
        'status' => NotificationStatus::Failed,
        'recipient' => 'ann@example.com',
        'payload' => ['event' => 'auth.password_reset', 'email' => 'ann@example.com', 'data' => ['expires_in' => 15]],
        'is_dlq' => true,
    ]);

    postJson('/api/v1/notifications/'.$reset->getKey().'/replay', [], roleHeaders('admin'))
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('code', 'notification_secret_redacted');

    Mail::assertNothingSent();
});

it('replays failed notification, updates replayed_by and replayed_at, and writes audit log', function (): void {
    Mail::fake();

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Log::listen(static function (MessageLogged $event) use (&$logged): void {
        $logged[] = [
            'level' => $event->level,
            'message' => $event->message,
            'context' => $event->context,
        ];
    });

    $log = seedNotificationLog([
        'event_id' => 'evt-replay-1',
        'user_id' => 7,
        'event' => 'user.registered',
        'status' => NotificationStatus::Failed,
        'recipient' => 'ann@example.com',
        'payload' => [
            'event' => 'user.registered',
            'email' => 'ann@example.com',
            'data' => ['name' => 'Ann'],
        ],
        'attempts' => 1,
        'is_dlq' => true,
        'error_message' => 'smtp down',
    ]);

    $id = (string) $log->getKey();

    postJson('/api/v1/notifications/'.$id.'/replay', [], roleHeaders('admin', 42))
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.is_dlq', false)
        ->assertJsonPath('data.replayed_by', 42);

    Mail::assertSent(UserRegisteredMail::class, static fn (UserRegisteredMail $mail): bool => $mail->hasTo('ann@example.com'));

    $updated = findNotificationLog($id);
    expect($updated->status)->toBe(NotificationStatus::Sent)
        ->and($updated->replayed_by)->toBe(42)
        ->and($updated->replayed_at)->not->toBeNull();

    expect($logged)->toContain([
        'level' => 'info',
        'message' => 'notification.replayed',
        'context' => [
            'notification_id' => $id,
            'actor_id' => 42,
        ],
    ]);
});

it('rejects replay of non-failed notification and disables swagger UI outside local env', function (): void {
    $sent = seedNotificationLog([
        'event' => 'user.registered',
        'status' => NotificationStatus::Sent,
        'recipient' => 'ann@example.com',
    ]);

    postJson('/api/v1/notifications/'.$sent->getKey().'/replay', [], roleHeaders('admin'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'notification_not_failed');

    getJson('/api/documentation')->assertNotFound();
});
