<?php

namespace Tests\Feature\Notifications;

use App\Events\RealtimeNotificationEvent;
use App\Kafka\Handlers\AuthNotificationHandler;
use App\Kafka\Handlers\OrderNotificationHandler;
use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Mockery;
use Tests\TestCase;

class NotificationHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NotificationLog::query()->delete();
        Event::fake([RealtimeNotificationEvent::class]);
    }

    protected function tearDown(): void
    {
        NotificationLog::query()->delete();
        parent::tearDown();
    }

    public function test_user_registered_is_stored_and_mailed(): void
    {
        Mail::fake();

        $this->handleAuth([
            'event' => 'user.registered',
            'user_id' => 7,
            'email' => 'new@example.com',
            'data' => ['name' => 'Ann'],
        ]);

        Mail::assertSent(UserRegisteredMail::class, function (UserRegisteredMail $mail) {
            return $mail->hasTo('new@example.com') && $mail->name === 'Ann';
        });

        $log = NotificationLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('user.registered', $log->event);
        $this->assertSame('new@example.com', $log->recipient);
        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
        $this->assertSame(1, (int) $log->attempts);
        $this->assertNotNull($log->sent_at);
        Event::assertDispatched(RealtimeNotificationEvent::class);
    }

    public function test_password_reset_and_two_factor_mails_are_sent(): void
    {
        Mail::fake();

        $this->handleAuth([
            'event' => 'auth.password_reset',
            'user_id' => 3,
            'email' => 'reset@example.com',
            'data' => ['reset_url' => 'http://localhost:3000/reset-password/token', 'expires_in' => 60],
        ]);

        $this->handleAuth([
            'event' => 'auth.two_factor_code',
            'user_id' => 3,
            'email' => 'reset@example.com',
            'data' => ['code' => '123456', 'ttl_minutes' => 5],
        ]);

        Mail::assertSent(PasswordResetMail::class);
        Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail) => $mail->code === '123456');
        $this->assertSame(2, NotificationLog::query()->where('status', NotificationLog::STATUS_SENT)->count());
    }

    public function test_order_events_are_stored_and_mailed(): void
    {
        Mail::fake();

        $this->handleOrder([
            'event' => 'order.paid',
            'user_id' => 4,
            'email' => 'buyer@example.com',
            'data' => ['order_id' => 15, 'amount' => 1200, 'currency' => 'BYN', 'items' => []],
        ]);
        $this->handleOrder([
            'event' => 'order.confirmed',
            'user_id' => 4,
            'email' => 'buyer@example.com',
            'data' => ['order_id' => 15, 'estimated_delivery' => '2026-10-02'],
        ]);
        $this->handleOrder([
            'event' => 'order.cancelled',
            'user_id' => 4,
            'email' => 'buyer@example.com',
            'data' => ['order_id' => 15, 'reason' => 'нет в наличии'],
        ]);

        Mail::assertSent(OrderPaidMail::class);
        Mail::assertSent(OrderConfirmedMail::class);
        Mail::assertSent(OrderCancelledMail::class, fn (OrderCancelledMail $mail) => $mail->reason === 'нет в наличии');
        $this->assertSame(3, NotificationLog::query()->where('status', NotificationLog::STATUS_SENT)->count());
    }

    public function test_failed_auth_event_is_retried_and_marked_dlq(): void
    {
        Kafka::fake();

        $this->handleAuth([
            'event' => 'unknown.event',
            'user_id' => 1,
            'email' => 'a@example.com',
            'data' => [],
        ]);

        $log = NotificationLog::query()->first();
        $this->assertSame(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertTrue((bool) $log->is_dlq);
        $this->assertSame(3, (int) $log->attempts);
        $this->assertNotEmpty($log->error_message);
        Kafka::assertPublishedOn('auth.notifications.dlq');
    }

    public function test_order_without_id_is_retried_and_marked_dlq(): void
    {
        Kafka::fake();

        $this->handleOrder([
            'event' => 'order.paid',
            'user_id' => 1,
            'email' => 'a@example.com',
            'data' => [],
        ]);

        $log = NotificationLog::query()->first();
        $this->assertSame(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertTrue((bool) $log->is_dlq);
        $this->assertSame(3, (int) $log->attempts);
        Kafka::assertPublishedOn('order.notifications.dlq');
    }

    private function handleAuth(array $body): void
    {
        (new AuthNotificationHandler)($this->message($body));
    }

    private function handleOrder(array $body): void
    {
        (new OrderNotificationHandler)($this->message($body));
    }

    private function message(array $body): ConsumerMessage
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);

        return $message;
    }
}
