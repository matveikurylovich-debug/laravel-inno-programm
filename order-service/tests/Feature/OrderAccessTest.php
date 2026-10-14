<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OutboxMessage;

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

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    expect(
        OutboxMessage::query()
            ->where('event_type', 'stock.release_requested')
            ->where('aggregate_id', $order->id)
            ->exists(),
    )->toBeTrue();
});

it('forbids a customer from deleting someone elses order', function () {
    $foreign = accessOrder(6);

    $this->withToken(orderJwt(5))
        ->deleteJson("/api/orders/{$foreign->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $foreign->id]);
});
