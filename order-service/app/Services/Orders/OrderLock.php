<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class OrderLock
{
    /**
     * @template T
     *
     * @param  callable(Order): T  $callback
     * @return T
     */
    public function run(string $orderId, callable $callback): mixed
    {
        return DB::transaction(function () use ($orderId, $callback) {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            return $callback($order);
        });
    }

    /**
     * @template T
     *
     * @param  callable(?Order): T  $callback
     * @return T
     */
    public function attempt(string $orderId, callable $callback): mixed
    {
        return DB::transaction(function () use ($orderId, $callback) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            return $callback($order);
        });
    }
}
