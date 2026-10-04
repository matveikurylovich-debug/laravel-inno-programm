<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StockReservationService
{
    /**
     * Попытка резервирования позиций под заказ.
     * Возвращает true в случае успеха, false если не хватило остатков.
     *
     * Статусы в БД: reserved, confirmed, released.
     * Уникальность брони: order_id + product_id.
     *
     * @param  array<array{product_id: int, quantity: int}>  $items
     */
    public function reserve(string $orderId, int $storeId, array $items): bool
    {
        try {
            return DB::transaction(function () use ($orderId, $storeId, $items) {
                $existingReservations = StockReservation::where('order_id', $orderId)->exists();
                if ($existingReservations) {
                    Log::info("Резерв для заказа {$orderId} уже был создан ранее.");

                    return true;
                }

                $lockedStocks = [];

                foreach ($items as $item) {
                    $productId = (int) $item['product_id'];
                    $requiredQty = (int) $item['quantity'];

                    /** @var Stock|null $stock */
                    $stock = Stock::where('product_id', $productId)
                        ->where('store_id', $storeId)
                        ->lockForUpdate()
                        ->first();

                    if (! $stock) {
                        Log::warning("Складская запись не найдена для product_id={$productId}, store_id={$storeId}");
                        throw new RuntimeException('stock-not-found');
                    }

                    $available = $stock->quantity - $stock->reserved_quantity;

                    if ($available < $requiredQty) {
                        Log::warning("Недостаточно остатка для товара {$productId}: запрошено {$requiredQty}, доступно {$available}");
                        throw new RuntimeException('insufficient-stock');
                    }

                    $lockedStocks[] = [
                        'stock' => $stock,
                        'quantity' => $requiredQty,
                    ];
                }

                foreach ($lockedStocks as $entry) {
                    /** @var Stock $stock */
                    $stock = $entry['stock'];
                    $quantity = $entry['quantity'];

                    $stock->increment('reserved_quantity', $quantity);

                    StockReservation::create([
                        'order_id' => $orderId,
                        'store_id' => $stock->store_id,
                        'product_id' => $stock->product_id,
                        'quantity' => $quantity,
                        'status' => 'reserved',
                    ]);
                }

                return true;
            });
        } catch (RuntimeException $exception) {
            if (in_array($exception->getMessage(), ['stock-not-found', 'insufficient-stock'], true)) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Финальное списание товара после успешной оплаты заказа.
     */
    public function commit(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return false;
            }

            foreach ($reservations as $reservation) {
                $stock = Stock::where('product_id', $reservation->product_id)
                    ->where('store_id', $reservation->store_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->decrement('quantity', $reservation->quantity);
                    $stock->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update(['status' => 'confirmed']);
            }

            return true;
        });
    }

    /**
     * Освобождение зарезервированного товара при отмене или сбое заказа.
     */
    public function release(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $reservations = StockReservation::where('order_id', $orderId)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return false;
            }

            foreach ($reservations as $reservation) {
                $stock = Stock::where('product_id', $reservation->product_id)
                    ->where('store_id', $reservation->store_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update(['status' => 'released']);
            }

            return true;
        });
    }
}
