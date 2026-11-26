<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;

beforeEach(function (): void {
    mockRedis();
});

function accessOrder(int $userId): Order
{
    return Order::query()->create([
        'user_id' => $userId,
        'email' => "user{$userId}@example.com",
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 10,
        'currency' => 'usd',
    ]);
}

it('shows a customer only their orders while admin and analyst see all', function (): void {
    $own = accessOrder(5);
    $foreign = accessOrder(6);

    $customer = $this->withToken(orderJwt(5))->getJson('/api/orders');
    $customer->assertOk();
    expect($customer->collect('data')->pluck('id')->all())->toBe([$own->id]);

    $this->withToken(orderJwt(5))
        ->getJson("/api/orders/{$foreign->id}")
        ->assertForbidden()
        ->assertHeader('content-type', 'application/problem+json');

    foreach (['admin', 'analyst'] as $role) {
        $response = $this->withToken(orderJwt(9, $role))->getJson('/api/orders');
        $response->assertOk();
        expect($response->collect('data')->pluck('id')->all())->toEqualCanonicalizing([$own->id, $foreign->id]);

        $this->withToken(orderJwt(9, $role))
            ->getJson("/api/orders/{$foreign->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $foreign->id);
    }
});

it('lets the owner cancel an order and request stock release without deleting the record', function (): void {
    $order = accessOrder(5);

    $this->withToken(orderJwt(5))
        ->postJson("/api/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => OrderStatus::Cancelled->value,
    ]);
    expect(
        OutboxMessage::query()
            ->where('event_type', 'stock.release_requested')
            ->where('aggregate_id', $order->id)
            ->exists(),
    )->toBeTrue();
});

it('rejects DELETE /api/orders/{order} because financial order records are never deleted', function (): void {
    $order = accessOrder(5);

    $this->withToken(orderJwt(5))
        ->deleteJson("/api/orders/{$order->id}")
        ->assertMethodNotAllowed();

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

it('hides the payment secret and buyer email from an analyst', function (): void {
    $order = accessOrder(6);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_secret',
        'stripe_client_secret' => 'cs_secret',
        'amount' => 10,
        'currency' => 'usd',
        'status' => 'pending',
    ]);

    $response = $this->withToken(orderJwt(9, 'analyst'))
        ->getJson("/api/orders/{$order->id}")
        ->assertOk();

    expect($response->json('data'))->not->toHaveKey('deleted_at')
        ->and($response->json('data.email'))->toBeNull()
        ->and($response->json('data.payment'))->not->toHaveKey('stripe_payment_intent_id')
        ->and($response->json('data.payment'))->not->toHaveKey('stripe_refund_id')
        ->and($response->json('data.payment'))->not->toHaveKey('raw_response')
        ->and($response->json('data.payment.stripe_client_secret'))->toBeNull();

    $owner = $this->withToken(orderJwt(6))
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.payment.stripe_client_secret', 'cs_secret');

    expect($owner->json('data'))->not->toHaveKey('deleted_at')
        ->and($owner->json('data.payment'))->not->toHaveKey('stripe_payment_intent_id');
});

it('forbids a customer from cancelling someone elses order', function (): void {
    $foreign = accessOrder(6);

    $this->withToken(orderJwt(5))
        ->postJson("/api/orders/{$foreign->id}/cancel")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $foreign->id,
        'status' => OrderStatus::Created->value,
    ]);
});
