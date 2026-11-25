<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;

beforeEach(function () {
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

it('shows a customer only their orders while admin and analyst see all', function () {
    $own = accessOrder(5);
    $foreign = accessOrder(6);

    $customer = $this->withToken(orderJwt(5))->getJson('/api/orders');
    $customer->assertOk();
    expect(collect($customer->json('data'))->pluck('id')->all())->toBe([$own->id]);

    $this->withToken(orderJwt(5))
        ->getJson("/api/orders/{$foreign->id}")
        ->assertForbidden();

    foreach (['admin', 'analyst'] as $role) {
        $response = $this->withToken(orderJwt(9, $role))->getJson('/api/orders');
        $response->assertOk();
        expect(collect($response->json('data'))->pluck('id')->all())->toEqualCanonicalizing([$own->id, $foreign->id]);

        $this->withToken(orderJwt(9, $role))
            ->getJson("/api/orders/{$foreign->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $foreign->id);
    }
});

it('lets the owner delete an order and request stock release', function () {
    $order = accessOrder(5);
    $order->update(['status' => 'payment_pending']);

    $this->withToken(orderJwt(5))
        ->deleteJson("/api/orders/{$order->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('orders', ['id' => $order->id]);
    expect(
        OutboxMessage::query()
            ->where('event_type', 'stock.release_requested')
            ->where('aggregate_id', $order->id)
            ->exists(),
    )->toBeTrue();
});

it('hides the payment secret and buyer email from an analyst', function () {
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

    expect($response->json('data.email'))->toBeNull()
        ->and($response->json('data.payment.stripe_client_secret'))->toBeNull();

    $this->withToken(orderJwt(6))
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.payment.stripe_client_secret', 'cs_secret');
});

it('forbids a customer from deleting someone elses order', function () {
    $foreign = accessOrder(6);

    $this->withToken(orderJwt(5))
        ->deleteJson("/api/orders/{$foreign->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $foreign->id]);
});
