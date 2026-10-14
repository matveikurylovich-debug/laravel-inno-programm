<?php

namespace Tests\Feature;

use App\Contracts\ProductIndex;
use App\Kafka\Consumers\CatalogEventsConsumer;
use App\Kafka\Consumers\StockEventsConsumer;
use Illuminate\Support\Facades\Redis;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    public function test_catalog_event_indexes_product_by_external_id(): void
    {
        $index = \Mockery::mock(ProductIndex::class);
        $index->shouldReceive('upsert')
            ->once()
            ->with('15', \Mockery::on(fn (array $document): bool => $document['name'] === 'Phone' && $document['id'] === '15'));
        $this->app->instance(ProductIndex::class, $index);

        app(CatalogEventsConsumer::class)($this->message([
            'event' => 'product.updated',
            'product_id' => 15,
            'store_id' => 1,
            'name' => 'Phone',
            'price' => 10,
        ]), \Mockery::mock(MessageConsumer::class));
    }

    public function test_repeated_delete_ignores_missing_document(): void
    {
        $index = \Mockery::mock(ProductIndex::class);
        $index->shouldReceive('delete')->once()->with('15');
        $this->app->instance(ProductIndex::class, $index);

        app(CatalogEventsConsumer::class)($this->message([
            'event' => 'product.deleted',
            'product_id' => 15,
        ]), \Mockery::mock(MessageConsumer::class));
    }

    public function test_stock_event_updates_availability(): void
    {
        $index = \Mockery::mock(ProductIndex::class);
        $index->shouldReceive('updateInStock')->once()->with('4', false);
        $this->app->instance(ProductIndex::class, $index);

        app(StockEventsConsumer::class)($this->message([
            'event' => 'stock.changed',
            'product_id' => 4,
            'quantity' => 0,
        ]), \Mockery::mock(MessageConsumer::class));
    }

    public function test_search_is_public_returns_facets_and_publishes_event(): void
    {
        $raw = [
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [['_source' => ['id' => '4', 'name' => 'Phone']]],
            ],
            'aggregations' => [
                'categories' => ['buckets' => []],
                'in_stock_count' => ['buckets' => ['true' => ['doc_count' => 1], 'false' => ['doc_count' => 0]]],
                'price_range' => ['min' => 10, 'max' => 10],
            ],
        ];

        $index = \Mockery::mock(ProductIndex::class);
        $index->shouldReceive('search')->once()->andReturn($raw);
        $this->app->instance(ProductIndex::class, $index);

        $stored = null;
        Redis::shouldReceive('get')->twice()->andReturnUsing(function () use (&$stored) {
            return $stored;
        });
        Redis::shouldReceive('setex')->once()->andReturnUsing(function (string $key, int $ttl, string $value) use (&$stored): bool {
            $stored = $value;

            return true;
        });

        $this->getJson('/api/search?q=phone')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Phone')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('facets.in_stock.true', 1);

        $this->getJson('/api/search?q=phone')->assertOk();

        Kafka::assertPublishedOn('search.events');
    }

    public function test_catalog_consumer_command_reads_fake_kafka(): void
    {
        if (! defined('RD_KAFKA_PARTITION_UA')) {
            define('RD_KAFKA_PARTITION_UA', -1);
        }

        $index = \Mockery::mock(ProductIndex::class);
        $index->shouldReceive('renameCategory')->once()->with(5, 'Phones');
        $this->app->instance(ProductIndex::class, $index);

        Kafka::fake();
        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'catalog.events',
                partition: 0,
                headers: ['event-type' => 'category.updated'],
                body: ['event' => 'category.updated', 'category_id' => 5, 'category_name' => 'Phones'],
                key: '5',
                offset: 0,
                timestamp: time(),
            ),
        ]);

        $this->artisan('kafka:consume-catalog')->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function message(array $body): ConsumerMessage
    {
        $message = \Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('getHeaders')->andReturn([]);

        return $message;
    }
}
