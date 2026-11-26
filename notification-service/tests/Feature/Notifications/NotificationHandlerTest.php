<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use App\Exceptions\NonRetryableException;
use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use App\Messaging\Consumers\AuthEventsConsumer;
use App\Messaging\Consumers\OrderEventsConsumer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use RdKafka\Conf;

/**
 * @param  array<string, mixed>|string  $body
 * @param  array<string, string>  $headers
 */
function makeKafkaMessage(array|string $body, array $headers = []): ConsumerMessage
{
    return new ConsumedMessage(
        topicName: 'test-topic',
        partition: 0,
        headers: $headers,
        body: $body,
        key: null,
        offset: 1,
        timestamp: time(),
    );
}

/**
 * @return array<string, mixed>
 */
function loadContractFixture(string $relativePath): array
{
    $fullPath = base_path('../contracts/'.$relativePath);
    $contents = file_get_contents($fullPath);
    if (! is_string($contents)) {
        throw new RuntimeException("Contract fixture not found: {$fullPath}");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException("Invalid contract fixture JSON: {$fullPath}");
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

it('consumes real order.events contract fixtures and saves user_id and event_id', function (): void {
    Mail::fake();

    $consumer = resolve(OrderEventsConsumer::class);
    $spyConsumer = new class implements MessageConsumer
    {
        public int $commits = 0;

        public function consume(): void {}

        public function stopConsuming(): void {}

        public function cancelStopConsume(): void {}

        public function consumedMessagesCount(): int
        {
            return $this->commits;
        }

        public function commit(mixed $message_or_offsets = null): void
        {
            $this->commits++;
        }

        public function commitAsync(mixed $message_or_offsets = null): void {}

        /**
         * @return array<int, mixed>
         */
        public function getAssignedPartitions(): array
        {
            return [];
        }

        /**
         * @param  array<string, mixed>  $options
         */
        public function setConf(array $options = []): Conf
        {
            return new Conf;
        }
    };

    $paidFixture = loadContractFixture('order.events/order.paid.json');
    $confirmedFixture = loadContractFixture('order.events/order.confirmed.json');
    $cancelledFixture = loadContractFixture('order.events/order.cancelled.json');
    $createdFixture = loadContractFixture('order.events/order.created.json');
    $refundedFixture = loadContractFixture('order.events/order.refunded.json');

    $consumer(makeKafkaMessage($paidFixture), $spyConsumer);
    $consumer(makeKafkaMessage($confirmedFixture), $spyConsumer);
    $consumer(makeKafkaMessage($cancelledFixture), $spyConsumer);
    $consumer(makeKafkaMessage($createdFixture), $spyConsumer);
    $consumer(makeKafkaMessage($refundedFixture), $spyConsumer);

    expect($spyConsumer->commits)->toBe(5);

    Mail::assertSent(OrderPaidMail::class, static fn (OrderPaidMail $mail): bool => $mail->hasTo('buyer@example.com')
        && $mail->orderId === '22222222-2222-4222-8222-222222222222'
        && $mail->amount === 1200.0
        && $mail->currency === 'BYN');

    Mail::assertSent(OrderConfirmedMail::class, static fn (OrderConfirmedMail $mail): bool => $mail->hasTo('buyer@example.com')
        && $mail->orderId === '22222222-2222-4222-8222-222222222222');

    Mail::assertSent(OrderCancelledMail::class, static fn (OrderCancelledMail $mail): bool => $mail->hasTo('buyer@example.com')
        && $mail->reason === 'cancelled_by_user');

    $logs = allNotificationLogs();
    expect($logs)->toHaveCount(3);

    foreach ($logs as $log) {
        expect($log->status)->toBe(NotificationStatus::Sent)
            ->and($log->user_id)->toBe(4)
            ->and($log->event_id)->not->toBeNull()
            ->and($log->payload)->toBeArray()
            ->and($log->attempts)->toBe(1);
    }
});

it('consumes user.registered contract fixture and deduplicates duplicate event_id', function (): void {
    Mail::fake();

    $fixture = loadContractFixture('auth.events/user.registered.json');
    $consumer = resolve(AuthEventsConsumer::class);

    $consumer(makeKafkaMessage($fixture));
    $consumer(makeKafkaMessage($fixture));

    Mail::assertSentCount(1);
    Mail::assertSent(UserRegisteredMail::class, static fn (UserRegisteredMail $mail): bool => $mail->hasTo('buyer@example.com')
        && $mail->name === 'Buyer');

    $logs = allNotificationLogs();
    expect($logs)->toHaveCount(1)
        ->and($logs[0]->event_id)->toBe('33333333-3333-4333-8333-333333333331')
        ->and($logs[0]->user_id)->toBe(4)
        ->and($logs[0]->status)->toBe(NotificationStatus::Sent);
});

it('sends password reset and two factor mails while redacting secrets before persistence', function (): void {
    Mail::fake();

    $consumer = resolve(AuthEventsConsumer::class);

    $consumer(makeKafkaMessage([
        'event_id' => 'auth-reset-1',
        'event' => 'auth.password_reset',
        'user_id' => 3,
        'email' => 'reset@example.com',
        'data' => [
            'reset_url' => 'http://localhost:3000/reset-password/secret-token',
            'expires_in' => 60,
        ],
    ]));

    $consumer(makeKafkaMessage([
        'event_id' => 'auth-2fa-1',
        'event' => 'auth.two_factor_code',
        'user_id' => 3,
        'email' => 'reset@example.com',
        'data' => [
            'code' => '123456',
            'ttl_minutes' => 5,
        ],
    ]));

    Mail::assertSent(PasswordResetMail::class, static fn (PasswordResetMail $mail): bool => $mail->expiresIn === 60);
    Mail::assertSent(TwoFactorCodeMail::class, static fn (TwoFactorCodeMail $mail): bool => $mail->code === '123456' && $mail->ttlMinutes === 5);

    foreach (allNotificationLogs() as $log) {
        expect($log->payload)->toBeArray();
        $payload = is_array($log->payload) ? $log->payload : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        expect($payload)->not->toHaveKey('code')
            ->and($payload)->not->toHaveKey('reset_url')
            ->and($data)->not->toHaveKey('code')
            ->and($data)->not->toHaveKey('reset_url');
    }
});

it('throws NonRetryableException immediately on invalid body or invalid contract without sleeping', function (): void {
    Sleep::fake();

    $authConsumer = resolve(AuthEventsConsumer::class);
    $orderConsumer = resolve(OrderEventsConsumer::class);

    expect(static fn () => $orderConsumer(makeKafkaMessage('raw-json-string')))
        ->toThrow(NonRetryableException::class);

    expect(static fn () => $authConsumer(makeKafkaMessage([
        'event' => 'unknown.event',
        'user_id' => 1,
        'email' => 'a@example.com',
        'data' => [],
    ])))->toThrow(NonRetryableException::class);

    expect(static fn () => $orderConsumer(makeKafkaMessage([
        'event' => 'order.paid',
        'user_id' => 1,
        'email' => 'a@example.com',
        'data' => [],
    ])))->toThrow(NonRetryableException::class);

    Sleep::assertNeverSlept();

    $logs = allNotificationLogs();
    expect($logs)->toHaveCount(2);

    foreach ($logs as $log) {
        expect($log->status)->toBe(NotificationStatus::Failed)
            ->and($log->is_dlq)->toBeTrue()
            ->and($log->attempts)->toBe(1);
    }
});

it('retries transient mail failures via ConsumeKafkaCommand using Sleep::fake without real pauses', function (): void {
    Sleep::fake();

    $fixture = loadContractFixture('order.events/order.paid.json');
    $calls = 0;

    Mail::shouldReceive('to')
        ->twice()
        ->with('buyer@example.com')
        ->andReturnUsing(static function () use (&$calls): object {
            return new class($calls)
            {
                public function __construct(private int &$calls) {}

                public function send(mixed $mailable): void
                {
                    $this->calls++;
                    if ($this->calls === 1) {
                        throw new RuntimeException('Temporary SMTP timeout');
                    }
                }
            };
        });

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        makeKafkaMessage($fixture),
    ]);

    expect(Artisan::call('notification:consume-order-events'))->toBe(0);

    Sleep::assertSleptTimes(1);

    $logs = allNotificationLogs();
    expect($logs)->toHaveCount(1)
        ->and($logs[0]->status)->toBe(NotificationStatus::Sent)
        ->and($logs[0]->attempts)->toBe(2);
});
