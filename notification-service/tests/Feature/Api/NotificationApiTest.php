<?php

namespace Tests\Feature\Api;

use App\Mail\Auth\UserRegisteredMail;
use App\Models\NotificationLog;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    private string $secret = 'test-secret-key-for-jwt-testing-12345';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.keys.access' => $this->secret, 'jwt.algo' => 'HS256']);
        NotificationLog::query()->delete();
    }

    protected function tearDown(): void
    {
        NotificationLog::query()->delete();
        parent::tearDown();
    }

    public function test_admin_lists_notifications_with_filters(): void
    {
        $this->log('user.registered', 'sent', 'ann@example.com', now());
        $this->log('order.paid', 'failed', 'bob@example.com', now()->subDays(3));
        $old = $this->log('order.cancelled', 'sent', 'ann@example.com', now()->subDays(20));

        $this->asRole('admin')
            ->getJson('/api/notifications?status=sent&recipient=ann')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['recipient' => 'ann@example.com', 'status' => 'sent']);

        $this->asRole('analyst')
            ->getJson('/api/notifications?status=failed&channel=email')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['recipient' => 'bob@example.com', 'status' => 'failed']);

        $recent = $this->asRole('admin')
            ->getJson('/api/notifications?'.http_build_query([
                'date_from' => now()->subDays(5)->toDateString(),
                'date_to' => now()->toDateString(),
                'channel' => 'email',
            ]))
            ->assertOk();

        $ids = collect($recent->json('data'))->pluck('id');
        $this->assertFalse($ids->contains((string) $old->_id));
        $this->assertCount(2, $ids);
    }

    public function test_show_returns_payload_and_missing_id_is_not_found(): void
    {
        $log = $this->log('user.registered', 'sent', 'ann@example.com', now(), [
            'event' => 'user.registered',
            'email' => 'ann@example.com',
            'data' => ['name' => 'Ann'],
        ]);

        $this->asRole('analyst')
            ->getJson('/api/notifications/'.$log->_id)
            ->assertOk()
            ->assertJsonPath('data.event', 'user.registered')
            ->assertJsonPath('data.payload.data.name', 'Ann');

        $this->asRole('admin')
            ->getJson('/api/notifications/missing-id')
            ->assertNotFound();
    }

    public function test_admin_can_replay_failed_notification(): void
    {
        Mail::fake();

        $log = $this->log('user.registered', 'failed', 'ann@example.com', now(), [
            'event' => 'user.registered',
            'email' => 'ann@example.com',
            'data' => ['name' => 'Ann'],
        ], true);

        $this->asRole('admin')
            ->postJson('/api/notifications/'.$log->_id.'/replay')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.is_dlq', false);

        Mail::assertSent(UserRegisteredMail::class, fn (UserRegisteredMail $mail) => $mail->hasTo('ann@example.com'));
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_replay_rejects_notifications_that_are_not_failed(): void
    {
        $log = $this->log('user.registered', 'sent', 'ann@example.com', now());

        $this->asRole('admin')
            ->postJson('/api/notifications/'.$log->_id.'/replay')
            ->assertStatus(422);

        $this->asRole('admin')
            ->postJson('/api/notifications/missing-id/replay')
            ->assertNotFound();
    }

    private function asRole(string $role): static
    {
        $token = JWT::encode([
            'iss' => 'http://localhost:8000',
            'sub' => 1,
            'roles' => [$role],
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + 900,
        ], $this->secret, 'HS256');

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function log(
        string $event,
        string $status,
        string $recipient,
        \DateTimeInterface $createdAt,
        array $payload = [],
        bool $dlq = false,
    ): NotificationLog {
        $log = NotificationLog::query()->create([
            'event' => $event,
            'channel' => 'email',
            'recipient' => $recipient,
            'payload' => $payload ?: ['event' => $event, 'email' => $recipient, 'data' => []],
            'status' => $status,
            'attempts' => $status === 'failed' ? 3 : 1,
            'is_dlq' => $dlq,
            'error_message' => $status === 'failed' ? 'smtp down' : null,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        $log->created_at = $createdAt;
        $log->save();

        return $log->fresh();
    }
}
