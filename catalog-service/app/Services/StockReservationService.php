<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\StoreLocation;
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
    public function reserve(string $orderId, int $storeId, array $items, ?int $locationId = null): bool
    {
        try {
            return DB::transaction(function () use ($orderId, $storeId, $items, $locationId) {
                $existingReservations = StockReservation::where('order_id', $orderId)->exists();
                if ($existingReservations) {
                    Log::info("Резерв для заказа {$orderId} уже был создан ранее.");

                    return true;
                }

                if ($locationId !== null && ! $this->locationBelongsToStore($locationId, $storeId)) {
                    Log::warning("Точка {$locationId} не принадлежит магазину {$storeId}");
                    throw new RuntimeException('stock-not-found');
                }

                $lockedStocks = [];

                foreach ($items as $item) {
                    $productId = (int) $item['product_id'];
                    $requiredQty = (int) $item['quantity'];

                    if (! Product::query()->whereKey($productId)->where('store_id', $storeId)->exists()) {
                        Log::warning("Товар {$productId} не принадлежит магазину {$storeId}");
                        throw new RuntimeException('stock-not-found');
                    }

                    $stockQuery = Stock::query()
                        ->where('product_id', $productId)
                        ->where('store_id', $storeId);

                    if ($locationId !== null) {
                        $stockQuery->where('location_id', $locationId);
                    } elseif ((clone $stockQuery)->count() !== 1) {
                        Log::warning("Для товара {$productId} не указана точка самовывоза магазина {$storeId}");
                        throw new RuntimeException('stock-not-found');
                    }

                    /** @var Stock|null $stock */
                    $stock = $stockQuery->lockForUpdate()->first();

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
                        'location_id' => $stock->location_id,
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
                $stock = $this->lockedReservationStock($reservation);

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
                $stock = $this->lockedReservationStock($reservation);

                if ($stock) {
                    $stock->decrement('reserved_quantity', $reservation->quantity);
                }

                $reservation->update(['status' => 'released']);
            }

            return true;
        });
    }

    private function lockedReservationStock(StockReservation $reservation): ?Stock
    {
        $query = Stock::query()
            ->where('product_id', $reservation->product_id)
            ->where('store_id', $reservation->store_id);

        if ($reservation->location_id !== null) {
            $query->where('location_id', $reservation->location_id);
        }

        return $query->lockForUpdate()->first();
    }

    private function locationBelongsToStore(int $locationId, int $storeId): bool
    {
        return StoreLocation::query()
            ->whereKey($locationId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();
    }
}
