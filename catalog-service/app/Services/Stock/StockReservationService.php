<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Data\ReservationItemData;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStock;
use App\Exceptions\StockNotFound;
use App\Messaging\Producers\InventoryEventsProducer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\StoreLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class StockReservationService
{
    public function __construct(
        private readonly StockAvailability $availability,
        private readonly InventoryEventsProducer $inventory,
    ) {}

    /**
     * Попытка резервирования позиций под заказ.
     * Возвращает true в случае успеха, false если не хватило остатков.
     *
     * Статусы в БД: reserved, confirmed, released.
     * Уникальность брони: order_id + product_id.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     */
    public function reserve(string $orderId, int $storeId, array $items, ?int $locationId = null): bool
    {
        try {
            return DB::transaction(function () use ($orderId, $storeId, $items, $locationId): bool {
                if ($this->alreadyReserved($orderId, $storeId)) {
                    return true;
                }

                $locked = $this->lockStocks($storeId, $this->summedItems($items), $locationId);
                $this->assertAvailable($locked);
                $this->applyReservation($orderId, $locked);
                $this->inventory->emitReserved($orderId, $storeId);

                return true;
            });
        } catch (StockNotFound|InsufficientStock $exception) {
            $this->inventory->emitFailed($orderId, $storeId, $exception->getMessage());

            return false;
        } catch (QueryException $exception) {
            if ($this->isConnectionFailure($exception)) {
                throw $exception;
            }

            $this->inventory->emitFailed($orderId, $storeId, 'internal_error');

            return false;
        }
    }

    /**
     * Финальное списание товара после успешной оплаты заказа.
     */
    public function commit(string $orderId, int $storeId = 0): bool
    {
        return DB::transaction(function () use ($orderId, $storeId): bool {
            $reservations = StockReservation::query()
                ->where('order_id', $orderId)
                ->where('status', ReservationStatus::Reserved)
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                $this->inventory->emitCommitFailed($orderId, $storeId, 'reservation-missing');

                return false;
            }

            $productIds = [];

            foreach ($reservations as $reservation) {
                $stock = $this->lockedReservationStock($reservation);

                if (! $stock instanceof Stock) {
                    throw new LogicException('stock-missing');
                }

                $quantity = (int) $reservation->quantity;
                Stock::query()->whereKey($stock->id)->decrementEach([
                    'quantity' => $quantity,
                    'reserved_quantity' => $quantity,
                ]);
                $productIds[] = (int) $stock->product_id;

                $reservation->update(['status' => ReservationStatus::Confirmed]);
            }

            foreach (array_unique($productIds) as $productId) {
                $this->availability->publish($productId);
            }

            $resolvedStore = $storeId > 0 ? $storeId : (int) $reservations->first()->store_id;
            $this->inventory->emitCommitted($orderId, $resolvedStore);

            return true;
        });
    }

    /**
     * Освобождение зарезервированного товара при отмене или сбое заказа.
     */
    public function release(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId): bool {
            $reservations = StockReservation::query()
                ->where('order_id', $orderId)
                ->whereIn('status', [ReservationStatus::Reserved, ReservationStatus::Confirmed])
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return false;
            }

            $productIds = [];

            foreach ($reservations as $reservation) {
                $stock = $this->lockedReservationStock($reservation);

                if ($stock instanceof Stock) {
                    if ($reservation->status === ReservationStatus::Confirmed) {
                        $stock->increment('quantity', $reservation->quantity);
                    } else {
                        $stock->decrement('reserved_quantity', $reservation->quantity);
                    }

                    $productIds[] = (int) $stock->product_id;
                }

                $reservation->update(['status' => ReservationStatus::Released]);
            }

            foreach (array_unique($productIds) as $productId) {
                $this->availability->publish($productId);
            }

            return true;
        });
    }

    private function alreadyReserved(string $orderId, int $storeId): bool
    {
        if (! StockReservation::query()->where('order_id', $orderId)->exists()) {
            return false;
        }

        Log::info("Резерв для заказа {$orderId} уже был создан ранее.");
        $this->inventory->emitReserved($orderId, $storeId);

        return true;
    }

    /**
     * @param  list<ReservationItemData>  $items
     * @return list<array{stock: Stock, item: ReservationItemData}>
     */
    private function lockStocks(int $storeId, array $items, ?int $locationId): array
    {
        if ($locationId !== null && ! $this->locationBelongsToStore($locationId, $storeId)) {
            Log::warning("Точка {$locationId} не принадлежит магазину {$storeId}");
            throw new StockNotFound;
        }

        $locked = [];

        foreach ($items as $item) {
            $locked[] = [
                'stock' => $this->lockedStock($storeId, $item, $locationId),
                'item' => $item,
            ];
        }

        return $locked;
    }

    /**
     * @param  list<array{stock: Stock, item: ReservationItemData}>  $lockedStocks
     */
    private function assertAvailable(array $lockedStocks): void
    {
        foreach ($lockedStocks as $entry) {
            $stock = $entry['stock'];
            $quantity = $entry['item']->quantity;
            $available = $stock->quantity - $stock->reserved_quantity;

            if ($available < $quantity) {
                Log::warning("Недостаточно остатка для товара {$stock->product_id}: запрошено {$quantity}, доступно {$available}");
                throw new InsufficientStock;
            }
        }
    }

    /**
     * @param  list<array{stock: Stock, item: ReservationItemData}>  $lockedStocks
     */
    private function applyReservation(string $orderId, array $lockedStocks): void
    {
        $productIds = [];

        foreach ($lockedStocks as $entry) {
            $stock = $entry['stock'];
            $quantity = $entry['item']->quantity;

            $stock->increment('reserved_quantity', $quantity);

            StockReservation::query()->create([
                'order_id' => $orderId,
                'store_id' => $stock->store_id,
                'location_id' => $stock->location_id,
                'product_id' => $stock->product_id,
                'quantity' => $quantity,
                'status' => ReservationStatus::Reserved,
            ]);

            $productIds[] = (int) $stock->product_id;
        }

        foreach (array_unique($productIds) as $productId) {
            $this->availability->publish($productId);
        }
    }

    private function lockedStock(int $storeId, ReservationItemData $item, ?int $locationId): Stock
    {
        $productId = $item->productId;
        $sellable = Product::query()
            ->whereKey($productId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereHas('store', fn (Builder $query) => $query->where('is_active', true))
            ->exists();

        if (! $sellable) {
            Log::warning("Товар {$productId} не принадлежит магазину {$storeId}");
            throw new StockNotFound;
        }

        $stockQuery = Stock::query()
            ->where('product_id', $productId)
            ->where('store_id', $storeId);

        if ($locationId !== null) {
            $stockQuery->where('location_id', $locationId);
        } elseif ((clone $stockQuery)->count() !== 1) {
            Log::warning("Для товара {$productId} не указана точка самовывоза магазина {$storeId}");
            throw new StockNotFound;
        }

        $stock = $stockQuery->lockForUpdate()->first();

        if ($stock === null) {
            Log::warning("Складская запись не найдена для product_id={$productId}, store_id={$storeId}");
            throw new StockNotFound;
        }

        return $stock;
    }

    private function lockedReservationStock(StockReservation $reservation): ?Stock
    {
        return Stock::query()
            ->where('product_id', $reservation->product_id)
            ->where('store_id', $reservation->store_id)
            ->where('location_id', $reservation->location_id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return list<ReservationItemData>
     */
    private function summedItems(array $items): array
    {
        $sums = [];

        foreach ($items as $item) {
            $line = ReservationItemData::fromArray($item);
            $sums[$line->productId] = ($sums[$line->productId] ?? 0) + $line->quantity;
        }

        $summed = [];

        foreach ($sums as $productId => $quantity) {
            $summed[] = new ReservationItemData($productId, $quantity);
        }

        return $summed;
    }

    private function locationBelongsToStore(int $locationId, int $storeId): bool
    {
        return StoreLocation::query()
            ->whereKey($locationId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();
    }

    private function isConnectionFailure(QueryException $exception): bool
    {
        $state = $exception->errorInfo[0] ?? '';

        return in_array($state, ['08000', '08003', '08006', '57P01', '40001'], true);
    }
}
