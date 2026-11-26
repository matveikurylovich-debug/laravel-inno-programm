<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Data\CreateOrderData;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ProductsUnavailable;
use App\Messaging\Messages\OrderCreated;
use App\Models\Order;
use App\Models\ProductSnapshot;
use App\Services\Outbox\OutboxService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrder
{
    public function __construct(
        private OutboxService $outbox,
        private OrderLock $lock,
    ) {}

    public function handle(CreateOrderData $data): Order
    {
        $merged = [];
        foreach ($data->lines as $line) {
            $productId = $line['product_id'];
            $merged[$productId] = ($merged[$productId] ?? 0) + $line['quantity'];
        }

        $lines = [];
        foreach ($merged as $productId => $quantity) {
            $lines[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        $productIds = array_keys($merged);
        $snapshots = ProductSnapshot::query()
            ->where('store_id', $data->storeId)
            ->where('is_active', true)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $missing = array_values(array_map(intval(...), array_diff($productIds, $snapshots->keys()->map(fn ($id): int => (int) $id)->all())));
        if ($missing !== []) {
            throw new ProductsUnavailable($missing);
        }

        return DB::transaction(function () use ($data, $lines, $snapshots): Order {
            $items = [];
            $total = '0.00';

            foreach ($lines as $line) {
                /** @var ProductSnapshot $snapshot */
                $snapshot = $snapshots->get($line['product_id']);
                $quantity = $line['quantity'];
                $unitPrice = Money::decimal($snapshot->price);
                $subtotal = Money::multiply($unitPrice, $quantity);
                $total = Money::add($total, $subtotal);

                $items[] = [
                    'product_id' => (int) $snapshot->product_id,
                    'product_name' => $snapshot->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $data->userId,
                'email' => $data->email,
                'store_id' => $data->storeId,
                'location_id' => $data->locationId,
                'status' => OrderStatus::Created,
                'payment_status' => PaymentStatus::Pending,
                'total_amount' => $total,
                'currency' => 'usd',
                'reserve_deadline' => now()->addMinutes((int) config('orders.reserve_timeout_minutes', 5)),
            ]);

            $order->items()->createMany($items);
            $order->load('items');

            $this->outbox->record(
                OrderEventType::Created->value,
                'Order',
                (string) $order->id,
                OrderCreated::toPayload($order),
            );

            return $order;
        });
    }

    public function republish(Order $order): Order
    {
        return $this->lock->run((string) $order->id, function (Order $order): Order {
            if ($order->status !== OrderStatus::Created) {
                return $order->load('items');
            }

            $order->load('items');
            $this->outbox->record(
                OrderEventType::Created->value,
                'Order',
                (string) $order->id,
                OrderCreated::toPayload($order),
            );
            $order->update([
                'reserve_republished_at' => now(),
                'reserve_deadline' => now()->addMinutes((int) config('orders.reserve_timeout_minutes', 5)),
            ]);

            return $order->fresh('items') ?? $order;
        });
    }
}
