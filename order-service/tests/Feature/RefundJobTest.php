<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundState;
use App\Jobs\RefundCancelledOrder;
use App\Messaging\Producers\AnalyticsEventsProducer;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxService;
use App\Support\RefundResult;
use Junges\Kafka\Contracts\MessageProducer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Factory;
use Junges\Kafka\Message\Message;

it('keeps a pending refund and does not create a second one', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::Paid,
        'total_amount' => 20,
        'currency' => 'usd',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_refund_job',
        'amount' => 20,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ]);

    $this->mock(PaymentGateway::class, function ($mock): void {
        $mock->shouldReceive('refund')->once()->andReturn(new RefundResult(RefundState::Pending, 're_pending'));
    });

    $job = new RefundCancelledOrder($order->id);
    expect($job->queue)->toBe('payments');
    $job->handle(resolve(PaymentGateway::class), resolve(OutboxService::class));
    $job->handle(resolve(PaymentGateway::class), resolve(OutboxService::class));

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::RefundPending)
        ->and($order->payment?->stripe_refund_id)->toBe('re_pending')
        ->and(OutboxMessage::query()->where('event_type', 'order.refunded')->count())->toBe(0);
});

it('records order.refunded matching contract fixture and relays to kafka and analytics on succeeded refund', function (): void {
    /** @var array{event_type: string, schema_version: int, producer: string, data: array{event: string, user_id: int, store_id: int, refunded_amount: string, currency: string}} $contract */
    $contract = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3).'/contracts/order.events/order.refunded.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $order = Order::query()->create([
        'user_id' => $contract['data']['user_id'],
        'email' => 'buyer@example.com',
        'store_id' => $contract['data']['store_id'],
        'status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::Paid,
        'total_amount' => $contract['data']['refunded_amount'],
        'currency' => strtolower($contract['data']['currency']),
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_refund_succeeded',
        'amount' => $contract['data']['refunded_amount'],
        'currency' => $contract['data']['currency'],
        'status' => PaymentStatus::Succeeded,
    ]);

    $this->mock(PaymentGateway::class, function ($mock) use ($contract): void {
        $mock->shouldReceive('refund')->once()->andReturn(new RefundResult(
            RefundState::Succeeded,
            're_succeeded',
            $contract['data']['refunded_amount'],
            $contract['data']['currency'],
        ));
    });

    $job = new RefundCancelledOrder($order->id);
    $job->handle(resolve(PaymentGateway::class), resolve(OutboxService::class));

    $message = OutboxMessage::query()->where('event_type', 'order.refunded')->first();
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->payment?->status)->toBe(PaymentStatus::Refunded)
        ->and($message)->not->toBeNull()
        ->and($message?->payload['order_id'])->toBe($order->id)
        ->and($message?->payload['user_id'])->toBe($contract['data']['user_id'])
        ->and($message?->payload['store_id'])->toBe($contract['data']['store_id'])
        ->and($message?->payload['refunded_amount'])->toBe($contract['data']['refunded_amount'])
        ->and($message?->payload['currency'])->toBe($contract['data']['currency']);

    $capturedEnvelope = null;
    Kafka::swap(new Factory);
    $builder = Mockery::mock(MessageProducer::class);
    $builder->shouldReceive('onTopic')->once()->with('order.events')->andReturnSelf();
    $builder->shouldReceive('withMessage')->once()->with(Mockery::on(function (Message $kafkaMessage) use (&$capturedEnvelope): bool {
        $capturedEnvelope = $kafkaMessage->getBody();

        return true;
    }))->andReturnSelf();
    $builder->shouldReceive('send')->once()->andReturn(true);
    Kafka::shouldReceive('publish')->once()->andReturn($builder);

    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')->once()->with(
        'order.refunded',
        Mockery::on(fn (array $body): bool => $body['order_id'] === $order->id
            && $body['user_id'] === $contract['data']['user_id']
            && $body['store_id'] === $contract['data']['store_id']
            && $body['refunded_amount'] === $contract['data']['refunded_amount']
            && $body['currency'] === $contract['data']['currency']
            && is_string($body['event_id']) && $body['event_id'] !== ''
            && is_string($body['occurred_at']) && $body['occurred_at'] !== ''),
        true,
    );

    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    expect($capturedEnvelope)->toBeArray()
        ->and(array_keys((array) $capturedEnvelope))->toBe(array_keys($contract))
        ->and($capturedEnvelope['event_type'])->toBe($contract['event_type'])
        ->and($capturedEnvelope['schema_version'])->toBe($contract['schema_version'])
        ->and($capturedEnvelope['producer'])->toBe($contract['producer'])
        ->and($capturedEnvelope['data']['event'])->toBe($contract['data']['event'])
        ->and($capturedEnvelope['data']['order_id'])->toBe($order->id)
        ->and($capturedEnvelope['data']['user_id'])->toBe($contract['data']['user_id'])
        ->and($capturedEnvelope['data']['store_id'])->toBe($contract['data']['store_id'])
        ->and($capturedEnvelope['data']['refunded_amount'])->toBe($contract['data']['refunded_amount'])
        ->and($capturedEnvelope['data']['currency'])->toBe($contract['data']['currency'])
        ->and(array_diff(array_keys($contract['data']), array_keys($capturedEnvelope['data'])))->toBe([]);
});

it('records order.refunded once when OrderService::handleRefund receives succeeded refund', function (): void {
    $order = Order::query()->create([
        'user_id' => 4,
        'email' => 'buyer@example.com',
        'store_id' => 2,
        'status' => OrderStatus::Cancelled,
        'payment_status' => PaymentStatus::RefundPending,
        'total_amount' => 35.50,
        'currency' => 'usd',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_service_refund',
        'stripe_refund_id' => 're_service_1',
        'amount' => 35.50,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ]);

    $refund = [
        'id' => 're_service_1',
        'object' => 'refund',
        'status' => 'succeeded',
        'amount' => 3550,
        'currency' => 'usd',
        'payment_intent' => 'pi_service_refund',
    ];

    resolve(OrderService::class)->handleRefund($refund);
    resolve(OrderService::class)->handleRefund($refund);

    $messages = OutboxMessage::query()->where('event_type', 'order.refunded')->get();
    $first = $messages->first();
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($messages)->toHaveCount(1)
        ->and($first?->payload['refunded_amount'])->toBe('35.50')
        ->and($first?->payload['currency'])->toBe('USD');
});
