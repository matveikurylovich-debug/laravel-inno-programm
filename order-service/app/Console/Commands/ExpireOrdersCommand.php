<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\CreateOrder;
use Illuminate\Console\Command;
use Override;

class ExpireOrdersCommand extends Command
{
    #[Override]
    protected $signature = 'order:expire-orders';

    #[Override]
    protected $description = 'Отменяет просроченную оплату и повторяет зависший резерв';

    public function handle(CancelOrder $cancelOrder, CreateOrder $createOrder): int
    {
        Order::query()
            ->where('status', OrderStatus::PaymentPending)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Order $order) use ($cancelOrder): void {
                $cancelOrder->cancel($order, 'payment_timeout');
            });

        Order::query()
            ->where('status', OrderStatus::Created)
            ->whereNotNull('reserve_deadline')
            ->where('reserve_deadline', '<=', now())
            ->orderBy('id')
            ->each(function (Order $order) use ($cancelOrder, $createOrder): void {
                if ($order->reserve_republished_at !== null) {
                    $cancelOrder->cancel($order, 'reserve_timeout');

                    return;
                }

                $createOrder->republish($order);
            });

        return self::SUCCESS;
    }
}
