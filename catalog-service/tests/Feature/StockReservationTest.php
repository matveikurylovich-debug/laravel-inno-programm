<?php

namespace Tests\Feature;

use App\Kafka\Consumers\OrderEventsHandler;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_commit_and_release(): void
    {
        [$store, $product] = $this->catalog();
        $service = app(StockReservationService::class);
        $orderId = (string) Str::uuid();

        $this->assertTrue($service->reserve($orderId, $store->id, [
            ['product_id' => $product->id, 'quantity' => 4],
        ]));
        $this->assertSame(4, Stock::query()->first()->reserved_quantity);
        $this->assertTrue($service->reserve($orderId, $store->id, [
            ['product_id' => $product->id, 'quantity' => 4],
        ]));
        $this->assertSame(4, Stock::query()->first()->reserved_quantity);

        $this->assertFalse($service->reserve((string) Str::uuid(), $store->id, [
            ['product_id' => $product->id, 'quantity' => 100],
        ]));

        $this->assertTrue($service->commit($orderId));
        $stock = Stock::query()->first();
        $this->assertSame(6, $stock->quantity);
        $this->assertSame(0, $stock->reserved_quantity);
        $this->assertSame('confirmed', StockReservation::query()->first()->status);

        $releaseId = (string) Str::uuid();
        $this->assertTrue($service->reserve($releaseId, $store->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]));
        $this->assertTrue($service->release($releaseId));
        $this->assertSame(0, Stock::query()->first()->reserved_quantity);
        $this->assertSame('released', StockReservation::query()->where('order_id', $releaseId)->value('status'));
    }

    public function test_order_created_publishes_stock_reserved(): void
    {
        [$store, $product] = $this->catalog();
        $orderId = (string) Str::uuid();

        $message = \Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn([
            'event' => 'order.created',
            'order_id' => $orderId,
            'store_id' => $store->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        app(OrderEventsHandler::class)($message);

        Kafka::assertPublishedOn('inventory.events');
        $this->assertSame(1, Stock::query()->first()->reserved_quantity);
    }

    public function test_stock_release_requested_frees_reservation(): void
    {
        [$store, $product] = $this->catalog();
        $orderId = (string) Str::uuid();
        app(StockReservationService::class)->reserve($orderId, $store->id, [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $message = \Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn([
            'event' => 'stock.release_requested',
            'order_id' => $orderId,
            'store_id' => $store->id,
        ]);

        app(OrderEventsHandler::class)($message);

        $this->assertSame(0, Stock::query()->first()->reserved_quantity);
        $this->assertSame('released', StockReservation::query()->value('status'));
    }

    /**
     * @return array{0: Store, 1: Product}
     */
    private function catalog(): array
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
        Stock::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        return [$store, $product];
    }
}
