<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\StockReservation;
use App\Services\Stock\StockReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Description('Снимает неподтверждённые резервы старше срока, который больше окна оплаты')]
#[Signature('catalog:release-expired-reservations')]
class ReleaseExpiredReservationsCommand extends Command
{
    public function handle(StockReservationService $reservations): int
    {
        $reservationTtl = (int) config('inventory.reservation_ttl_minutes');
        $paymentTtl = (int) config('inventory.payment_ttl_minutes');

        if ($reservationTtl <= $paymentTtl) {
            throw new RuntimeException('TTL резерва должен быть больше срока оплаты');
        }

        $orderIds = StockReservation::query()
            ->where('status', ReservationStatus::Reserved)
            ->where('created_at', '<=', now()->subMinutes($reservationTtl))
            ->pluck('order_id')
            ->unique()
            ->values();

        foreach ($orderIds as $orderId) {
            $reservations->release((string) $orderId);
        }

        $this->info('Проверено резервов: '.$orderIds->count());

        return self::SUCCESS;
    }
}
