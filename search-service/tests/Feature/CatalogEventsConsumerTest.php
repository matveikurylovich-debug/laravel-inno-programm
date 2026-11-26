<?php

declare(strict_types=1);

use App\Contracts\ProductIndex;
use App\Exceptions\NonRetryableException;
use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\CatalogEventsConsumer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Junges\Kafka\Config\Config;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Junges\Kafka\Support\Testing\Fakes\BuilderFake;
use Mockery\MockInterface;

use function Pest\Laravel\getJson;
use function Pest\Laravel\swap;

/**
 * @param  array<string, mixed>  $body
 */
function catalogConsumerMessage(array $body): ConsumerMessage
{
    /** @var ConsumerMessage&MockInterface $message */
    $message = Mockery::mock(ConsumerMessage::class);
    $message->shouldReceive('getBody')->andReturn($body);
    $message->shouldReceive('getHeaders')->andReturn([]);

    return $message;
}

function catalogKafkaConsumerMock(): MessageConsumer
{
    /** @var MessageConsumer&MockInterface $consumer */
    $consumer = Mockery::mock(MessageConsumer::class);

    return $consumer;
}

function inMemoryProductIndex(): ProductIndex
{
    return new class implements ProductIndex
    {
        /** @var array<string, array<string, mixed>> */
        private array $docs = [];

        public function indexName(): string
        {
            return 'products';
        }

        public function upsert(string $id, array $document): void
        {
            $this->docs[$id] = $document;
        }

        public function delete(string $id): void
        {
            unset($this->docs[$id]);
        }

        public function updateInStock(string $id, bool $inStock): void
        {
            if (isset($this->docs[$id])) {
                $this->docs[$id]['is_in_stock'] = $inStock;
            }
        }

        public function renameCategory(int $categoryId, string $name): void {}

        public function clearCategory(int $categoryId): void {}

        public function search(array $query): array
        {
            /** @var list<array<string, mixed>> $filters */
            $filters = $query['query']['bool']['filter'] ?? [];
            $matched = array_values(array_filter($this->docs, static function (array $doc) use ($filters): bool {
                foreach ($filters as $clause) {
                    if (isset($clause['term']) && is_array($clause['term'])) {
                        foreach ($clause['term'] as $field => $expected) {
                            $actual = $doc[$field] ?? null;
                            if (is_array($actual)) {
                                if (! in_array($expected, $actual, true)) {
                                    return false;
                                }
                            } elseif ($actual !== $expected) {
                                return false;
                            }
                        }
                    }
                }

                return true;
            }));

            return [
                'took' => 1,
                'hits' => [
                    'total' => ['value' => count($matched)],
                    'hits' => array_map(static fn (array $doc): array => ['_source' => $doc], $matched),
                ],
                'aggregations' => [
                    'categories' => ['buckets' => []],
                    'in_stock_count' => ['buckets' => ['true' => ['doc_count' => count($matched)], 'false' => ['doc_count' => 0]]],
                    'price_range' => ['min' => null, 'max' => null],
                ],
            ];
        }

        public function setup(bool $force = false): void {}

        public function rebuild(iterable $documents): void {}
    };
}

it('removes inactive product from the index', function (): void {
    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('delete')->once()->with('15');
    $index->shouldReceive('upsert')->never();
    swap(ProductIndex::class, $index);

    resolve(CatalogEventsConsumer::class)(catalogConsumerMessage([
        'event' => 'product.updated',
        'product_id' => 15,
        'name' => 'Phone',
        'is_active' => false,
    ]), catalogKafkaConsumerMock());
});

it('indexes product by external id, deletes product, updates categories and stock', function (): void {
    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('upsert')
        ->once()
        ->with('15', Mockery::on(static fn (array $document): bool => $document['name'] === 'Phone' && $document['id'] === '15'));
    $index->shouldReceive('delete')->once()->with('15');
    $index->shouldReceive('renameCategory')->once()->with(5, 'Phones');
    $index->shouldReceive('clearCategory')->once()->with(5);
    $index->shouldReceive('updateInStock')->once()->ordered()->with('4', false);
    $index->shouldReceive('updateInStock')->once()->ordered()->with('4', true);
    swap(ProductIndex::class, $index);

    $consumer = resolve(CatalogEventsConsumer::class);
    $kafkaConsumer = catalogKafkaConsumerMock();

    $consumer(catalogConsumerMessage([
        'event' => 'product.updated',
        'product_id' => 15,
        'store_id' => 1,
        'name' => 'Phone',
        'price' => 10,
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'product.deleted',
        'product_id' => 15,
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'category.created',
        'category_id' => 5,
        'category_name' => 'Phones',
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'category.deleted',
        'category_id' => 5,
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'stock.changed',
        'product_id' => 4,
        'quantity' => 5,
        'available' => 0,
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'stock.changed',
        'product_id' => 4,
        'available' => 2,
    ]), $kafkaConsumer);
});

it('filters products by category_path subtree', function (): void {
    Queue::fake();
    swap(ProductIndex::class, inMemoryProductIndex());

    $consumer = resolve(CatalogEventsConsumer::class);
    $kafkaConsumer = catalogKafkaConsumerMock();

    $consumer(catalogConsumerMessage([
        'event' => 'product.created',
        'product_id' => 10,
        'store_id' => 1,
        'category_id' => 3,
        'category_path' => [1, 3],
        'category_name' => 'iOS',
        'name' => 'iPhone 16',
        'price' => 999,
        'available' => 5,
        'is_active' => true,
    ]), $kafkaConsumer);

    $consumer(catalogConsumerMessage([
        'event' => 'product.created',
        'product_id' => 11,
        'store_id' => 1,
        'category_id' => 4,
        'category_path' => [2, 4],
        'category_name' => 'Kitchen',
        'name' => 'Kettle',
        'price' => 50,
        'available' => 3,
        'is_active' => true,
    ]), $kafkaConsumer);

    getJson('/api/search?category_id=1&sort=price_desc')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'facets'])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', '10')
        ->assertJsonPath('data.0.category_path', [1, 3])
        ->assertJsonPath('meta.total', 1);

    getJson('/api/search?category_id=99')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('retries transient handler exceptions via Sleep::fake() and commits once recovered', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 2,
        'kafka.failed_message_backoff_ms' => 500,
    ]);

    $attempts = 0;
    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('upsert')->times(3)->andReturnUsing(static function () use (&$attempts): void {
        $attempts++;
        if ($attempts < 3) {
            throw new RuntimeException('transient es timeout');
        }
    });
    swap(ProductIndex::class, $index);

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'catalog.events',
            partition: 0,
            headers: ['event-type' => 'product.updated'],
            body: ['event' => 'product.updated', 'product_id' => 15, 'name' => 'Phone', 'is_active' => true],
            key: '15',
            offset: 0,
            timestamp: time(),
        ),
    ]);

    expect(Artisan::call('search:consume-catalog-events'))->toBe(0)
        ->and($attempts)->toBe(3);
    Sleep::assertSleptTimes(2);
});

it('exhausts retries via Sleep::fake() and rethrows for laravel-kafka DLQ with manual commit', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 2,
        'kafka.failed_message_backoff_ms' => 250,
    ]);

    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('upsert')->times(3)->andThrow(new RuntimeException('elasticsearch cluster down'));
    swap(ProductIndex::class, $index);

    $message = new ConsumedMessage(
        topicName: 'catalog.events',
        partition: 0,
        headers: ['event-type' => 'product.updated'],
        body: ['event' => 'product.updated', 'product_id' => 15, 'name' => 'Phone', 'is_active' => true],
        key: '15',
        offset: 0,
        timestamp: time(),
    );

    /** @var BuilderFake|null $capturedBuilder */
    $capturedBuilder = null;
    Kafka::shouldReceive('consumer')
        ->once()
        ->with(['catalog.events'])
        ->andReturnUsing(static function (array $topics) use ($message, &$capturedBuilder): BuilderFake {
            $capturedBuilder = BuilderFake::create('kafka:9092', $topics, 'search-service-catalog-group')
                ->setMessages([$message]);

            return $capturedBuilder;
        });

    expect(static fn () => ConsumerFactory::consume(
        'catalog.events',
        'search-service-catalog-group',
        'catalog.events.dlq.search-service',
        resolve(CatalogEventsConsumer::class),
    ))->toThrow(RuntimeException::class, 'elasticsearch cluster down');

    Sleep::assertSleptTimes(2);

    if (! $capturedBuilder instanceof BuilderFake) {
        throw new RuntimeException('Expected BuilderFake to be captured');
    }

    $builtConsumer = $capturedBuilder->build();
    $configProperty = new ReflectionProperty($builtConsumer, 'config');
    /** @var Config $consumerConfig */
    $consumerConfig = $configProperty->getValue($builtConsumer);

    expect($consumerConfig->getDlq())->toBe('catalog.events.dlq.search-service')
        ->and($consumerConfig->isAutoCommit())->toBeFalse();
});

it('routes NonRetryableException immediately to DLQ without sleeping or retrying', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 3,
        'kafka.failed_message_backoff_ms' => 500,
    ]);

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'catalog.events',
            partition: 0,
            headers: ['event-type' => 'stock.changed'],
            body: ['event' => 'stock.changed', 'product_id' => 4],
            key: '4',
            offset: 0,
            timestamp: time(),
        ),
    ]);

    expect(static fn () => ConsumerFactory::consume(
        'catalog.events',
        'search-service-catalog-group',
        'catalog.events.dlq.search-service',
        resolve(CatalogEventsConsumer::class),
    ))->toThrow(NonRetryableException::class);

    Sleep::assertNeverSlept();
});
