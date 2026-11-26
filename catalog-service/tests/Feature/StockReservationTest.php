<?php

declare(strict_types=1);

use App\Console\Commands\RelayOutboxCommand;
use App\Enums\ReservationStatus;
use App\Exceptions\NonRetryableException;
use App\Messaging\Consumers\OrderEventsConsumer;
use App\Models\CatalogOutbox;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Services\Stock\StockReservationService;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

/**
 * @param  array<string, mixed>  $body
 */
function orderKafkaMessage(array $body): ConsumedMessage
{
    return new ConsumedMessage(
        topicName: 'order.events',
        partition: 0,
        headers: [],
        body: $body,
        key: isset($body['order_id']) && is_string($body['order_id']) ? $body['order_id'] : 'order-key',
        offset: 1,
        timestamp: 1,
    );
}

/**
 * @return array{0: Store, 1: Product}
 */
function createReservationCatalog(): array
{
    $store = Store::query()->create([
        'name' => 'Shop',
        'slug' => 'shop',
        'is_active' => true,
    ]);
    $category = Category::query()->create([
        'store_id' => $store->id,
        'name' => 'Phones',
        'slug' => 'phones',
    ]);
    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'SKU-1',
        'name' => 'Phone',
        'slug' => 'phone',
        'price' => 10,
        'is_active' => true,
        'attributes' => ['brand' => 'Acme'],
    ]);
    $location = StoreLocation::query()->create([
        'store_id' => $store->id,
        'name' => 'Main',
        'address' => 'A',
        'is_active' => true,
    ]);
    Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    return [$store, $product];
}

/**
 * @return array{0: Store, 1: Product, 2: StoreLocation, 3: StoreLocation}
 */
function createReservationCatalogWithLocations(): array
{
    [$store, $product] = createReservationCatalog();
    $first = StoreLocation::query()->create([
        'store_id' => $store->id,
        'name' => 'One',
        'address' => 'A',
        'is_active' => true,
    ]);
    $second = StoreLocation::query()->create([
        'store_id' => $store->id,
        'name' => 'Two',
        'address' => 'B',
        'is_active' => true,
    ]);
    Stock::query()->delete();
    Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $first->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);
    Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $second->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'reserved_quantity' => 0,
    ]);

    return [$store, $product, $first, $second];
}

/**
 * @return array<string, mixed>
 */
function latestStockChangedPayload(): array
{
    $row = CatalogOutbox::query()->where('event', 'stock.changed')->orderByDesc('id')->firstOrFail();

    return $row->payload;
}

it('reserves commits and releases stock idempotently', function (): void {
    [$store, $product] = createReservationCatalog();
    $service = resolve(StockReservationService::class);
    $orderId = (string) Str::uuid();

    expect($service->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 4],
    ]))->toBeTrue();
    expect(Stock::query()->firstOrFail()->reserved_quantity)->toBe(4);

    expect($service->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 4],
    ]))->toBeTrue();
    expect(Stock::query()->firstOrFail()->reserved_quantity)->toBe(4);

    expect($service->reserve((string) Str::uuid(), $store->id, [
        ['product_id' => $product->id, 'quantity' => 100],
    ]))->toBeFalse();

    expect($service->commit($orderId))->toBeTrue();
    $stock = Stock::query()->firstOrFail();
    expect($stock->quantity)->toBe(6)
        ->and($stock->reserved_quantity)->toBe(0)
        ->and(StockReservation::query()->firstOrFail()->status)->toBe(ReservationStatus::Confirmed);

    $releaseId = (string) Str::uuid();
    expect($service->reserve($releaseId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]))->toBeTrue();
    expect($service->release($releaseId))->toBeTrue()
        ->and(Stock::query()->firstOrFail()->reserved_quantity)->toBe(0)
        ->and(StockReservation::query()->where('order_id', $releaseId)->value('status'))->toBe(ReservationStatus::Released);
});

it('publishes stock.reserved on order.created', function (): void {
    [$store, $product] = createReservationCatalog();
    $orderId = (string) Str::uuid();

    $message = orderKafkaMessage([
        'event' => 'order.created',
        'order_id' => $orderId,
        'store_id' => $store->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    resolve(OrderEventsConsumer::class)($message);

    expect(CatalogOutbox::query()->where('event', 'stock.reserved')->first())->not->toBeNull()
        ->and(CatalogOutbox::query()->where('event', 'stock.changed')->first())->not->toBeNull();

    resolve(RelayOutboxCommand::class)->publishBatch(20);
    Kafka::assertPublishedOn('inventory.events', null, static function (object $published): bool {
        $rawBody = method_exists($published, 'getBody') ? $published->getBody() : [];
        $body = EventEnvelope::flatten(is_array($rawBody) ? $rawBody : []);

        return ($body['event'] ?? null) === 'stock.reserved';
    });
    expect(Stock::query()->firstOrFail()->reserved_quantity)->toBe(1);
});

it('publishes stock.reservation_failed on insufficient stock', function (): void {
    [$store, $product] = createReservationCatalog();
    $orderId = (string) Str::uuid();

    $message = orderKafkaMessage([
        'event' => 'order.created',
        'order_id' => $orderId,
        'store_id' => $store->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100]],
    ]);

    resolve(OrderEventsConsumer::class)($message);

    $failed = CatalogOutbox::query()->where('event', 'stock.reservation_failed')->first();
    expect($failed)->not->toBeNull()
        ->and($failed?->payload['reason'])->toBe('Insufficient stock');

    resolve(RelayOutboxCommand::class)->publishBatch(20);
    Kafka::assertPublishedOn('inventory.events', null, static function (object $published): bool {
        $rawBody = method_exists($published, 'getBody') ? $published->getBody() : [];
        $body = EventEnvelope::flatten(is_array($rawBody) ? $rawBody : []);

        return ($body['event'] ?? null) === 'stock.reservation_failed';
    });
    expect(Stock::query()->firstOrFail()->reserved_quantity)->toBe(0)
        ->and(StockReservation::query()->count())->toBe(0);
});

it('publishes stock.committed on order.paid', function (): void {
    [$store, $product] = createReservationCatalog();
    $orderId = (string) Str::uuid();
    resolve(StockReservationService::class)->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]);

    $message = orderKafkaMessage([
        'event' => 'order.paid',
        'order_id' => $orderId,
        'store_id' => $store->id,
    ]);

    resolve(OrderEventsConsumer::class)($message);

    expect(CatalogOutbox::query()->where('event', 'stock.committed')->first())->not->toBeNull()
        ->and(CatalogOutbox::query()->where('event', 'stock.changed')->first())->not->toBeNull();

    resolve(RelayOutboxCommand::class)->publishBatch(20);
    Kafka::assertPublishedOn('inventory.events', null, static function (object $published): bool {
        $rawBody = method_exists($published, 'getBody') ? $published->getBody() : [];
        $body = EventEnvelope::flatten(is_array($rawBody) ? $rawBody : []);

        return ($body['event'] ?? null) === 'stock.committed';
    });
    expect(Stock::query()->firstOrFail()->quantity)->toBe(8)
        ->and(Stock::query()->firstOrFail()->reserved_quantity)->toBe(0);
});

it('frees reservation on stock.release_requested', function (): void {
    [$store, $product] = createReservationCatalog();
    $orderId = (string) Str::uuid();
    resolve(StockReservationService::class)->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 3],
    ]);

    $message = orderKafkaMessage([
        'event' => 'stock.release_requested',
        'order_id' => $orderId,
        'store_id' => $store->id,
    ]);

    resolve(OrderEventsConsumer::class)($message);

    expect(Stock::query()->firstOrFail()->reserved_quantity)->toBe(0)
        ->and(StockReservation::query()->value('status'))->toBe(ReservationStatus::Released)
        ->and(CatalogOutbox::query()->where('event', 'stock.changed')->first())->not->toBeNull();
});

it('publishes aggregate stock across locations on reserve commit and release', function (): void {
    [$store, $product, $first, $second] = createReservationCatalogWithLocations();
    $service = resolve(StockReservationService::class);
    $orderId = (string) Str::uuid();

    expect($service->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 4],
    ], $first->id))->toBeTrue();

    $reserved = latestStockChangedPayload();
    expect($reserved['available'])->toBe(9)
        ->and($reserved['is_in_stock'])->toBeTrue()
        ->and($reserved['locations'][0]['available'])->toBe(6)
        ->and($reserved['locations'][1]['available'])->toBe(3)
        ->and(array_sum(array_column($reserved['locations'], 'available')))->toBe($reserved['available']);

    /** @var array{locations: list<array<string, mixed>>} $fixture */
    $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/stock.changed.json')), true);
    $this->assertEqualsCanonicalizing(array_keys($fixture), array_keys($reserved));
    $this->assertEqualsCanonicalizing(array_keys($fixture['locations'][0]), array_keys($reserved['locations'][0]));
    expect($reserved['event'])->toBe('stock.changed');

    expect($service->commit($orderId, $store->id))->toBeTrue();
    expect(latestStockChangedPayload()['available'])->toBe(9);

    $firstRelease = (string) Str::uuid();
    expect($service->reserve($firstRelease, $store->id, [
        ['product_id' => $product->id, 'quantity' => 6],
    ], $first->id))->toBeTrue();
    expect(latestStockChangedPayload()['available'])->toBe(3);

    $secondRelease = (string) Str::uuid();
    expect($service->reserve($secondRelease, $store->id, [
        ['product_id' => $product->id, 'quantity' => 3],
    ], $second->id))->toBeTrue();
    expect(latestStockChangedPayload()['available'])->toBe(0)
        ->and(latestStockChangedPayload()['is_in_stock'])->toBeFalse();

    expect($service->release($secondRelease))->toBeTrue();
    expect(latestStockChangedPayload()['available'])->toBe(3);

    expect($service->release($firstRelease))->toBeTrue();
    $released = latestStockChangedPayload();
    expect($released['available'])->toBe(9)
        ->and($released['is_in_stock'])->toBeTrue()
        ->and($released['locations'][1]['location_id'])->toBe($second->id);
});

it('throws NonRetryableException when order event misses order_id', function (): void {
    $message = orderKafkaMessage([
        'event' => 'order.created',
        'store_id' => 1,
    ]);

    expect(static fn () => resolve(OrderEventsConsumer::class)($message))->toThrow(NonRetryableException::class);
});

it('publishes stock.commit_failed when committing without reservation', function (): void {
    [$store] = createReservationCatalog();

    expect(resolve(StockReservationService::class)->commit((string) Str::uuid(), $store->id))->toBeFalse()
        ->and(CatalogOutbox::query()->where('event', 'stock.commit_failed')->first())->not->toBeNull();
});

it('releases expired reservation with stock.changed event', function (): void {
    config([
        'inventory.payment_ttl_minutes' => 30,
        'inventory.reservation_ttl_minutes' => 45,
    ]);
    [$store, $product] = createReservationCatalog();
    $orderId = (string) Str::uuid();
    resolve(StockReservationService::class)->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]);
    StockReservation::query()->update(['created_at' => now()->subMinutes(50)]);
    CatalogOutbox::query()->delete();

    $this->artisan('catalog:release-expired-reservations')->assertSuccessful();

    expect(StockReservation::query()->value('status'))->toBe(ReservationStatus::Released)
        ->and(Stock::query()->firstOrFail()->reserved_quantity)->toBe(0)
        ->and(latestStockChangedPayload()['available'])->toBe(10);
});

it('requires reservation TTL to exceed the payment window', function (): void {
    config([
        'inventory.payment_ttl_minutes' => 30,
        'inventory.reservation_ttl_minutes' => 30,
    ]);

    expect(function (): void {
        $this->artisan('catalog:release-expired-reservations');
    })->toThrow(RuntimeException::class);
});

it('sums duplicate order lines into a single reservation', function (): void {
    [$store, $product] = createReservationCatalog();
    $locationId = (int) StoreLocation::query()->value('id');
    $orderId = (string) Str::uuid();

    $reserved = resolve(StockReservationService::class)->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 2],
        ['product_id' => $product->id, 'quantity' => 3],
    ], $locationId);

    expect($reserved)->toBeTrue()
        ->and(StockReservation::query()->count())->toBe(1)
        ->and((int) StockReservation::query()->value('quantity'))->toBe(5)
        ->and((int) Stock::query()->value('reserved_quantity'))->toBe(5);
});

it('deducts stock from the exact location holding the reservation on commit', function (): void {
    [$store, $product, $first, $second] = createReservationCatalogWithLocations();
    $orderId = (string) Str::uuid();
    $service = resolve(StockReservationService::class);

    expect($service->reserve($orderId, $store->id, [
        ['product_id' => $product->id, 'quantity' => 2],
    ], $first->id))->toBeTrue();

    Stock::query()->where('location_id', $second->id)->update(['quantity' => 10]);

    expect($service->commit($orderId, $store->id))->toBeTrue()
        ->and((int) Stock::query()->where('location_id', $first->id)->value('quantity'))->toBe(8)
        ->and((int) Stock::query()->where('location_id', $first->id)->value('reserved_quantity'))->toBe(0)
        ->and((int) Stock::query()->where('location_id', $second->id)->value('quantity'))->toBe(10)
        ->and((int) Stock::query()->where('location_id', $second->id)->value('reserved_quantity'))->toBe(0);
});
