<?php

declare(strict_types=1);

use App\Contracts\ProductIndex;
use App\Exceptions\SearchUnavailableException;
use App\Jobs\PublishSearchEvent;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\swap;

it('rejects page=10001 with 422 problem+json', function (): void {
    getJson('/api/search?page=10001')
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors'])
        ->assertJsonValidationErrors('page');
});

it('accepts in_stock=true and validates filter parameters', function (): void {
    Queue::fake();

    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('search')->times(3)->andReturn([
        'took' => 3,
        'hits' => ['total' => ['value' => 0], 'hits' => []],
        'aggregations' => [
            'categories' => ['buckets' => []],
            'in_stock_count' => ['buckets' => ['true' => ['doc_count' => 0], 'false' => ['doc_count' => 0]]],
            'price_range' => ['min' => null, 'max' => null],
        ],
    ]);
    swap(ProductIndex::class, $index);

    getJson('/api/search?in_stock=true')->assertOk();
    getJson('/api/search?in_stock=false')->assertOk();
    getJson('/api/search?in_stock=1')->assertOk();
    getJson('/api/search?in_stock=0')->assertOk();
    getJson('/api/search?price_max=100')->assertOk();

    getJson('/api/search?in_stock=yes')
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonValidationErrors('in_stock');

    getJson('/api/search?price_min=200&price_max=100')
        ->assertStatus(422)
        ->assertJsonValidationErrors('price_max');

    getJson('/api/search?sort=invalid')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

it('returns 503 problem+json when elasticsearch is unavailable', function (): void {
    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('search')->once()->andThrow(new SearchUnavailableException('elasticsearch down'));
    swap(ProductIndex::class, $index);

    getJson('/api/search?q=phone')
        ->assertStatus(503)
        ->assertHeader('content-type', 'application/problem+json');
});

it('degrades gracefully to elasticsearch with 200 when cache repository throws RuntimeException', function (): void {
    Queue::fake();

    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('search')->once()->andReturn([
        'took' => 14,
        'hits' => [
            'total' => ['value' => 1],
            'hits' => [['_source' => ['id' => '4', 'name' => 'Phone']]],
        ],
        'aggregations' => [
            'categories' => ['buckets' => []],
            'in_stock_count' => ['buckets' => ['true' => ['doc_count' => 1], 'false' => ['doc_count' => 0]]],
            'price_range' => ['min' => 10, 'max' => 10],
        ],
    ]);
    swap(ProductIndex::class, $index);

    $brokenCache = Mockery::mock(Repository::class);
    $brokenCache->shouldReceive('get')->once()->andThrow(new RuntimeException('redis down'));
    $brokenCache->shouldReceive('put')->once()->andThrow(new RuntimeException('redis down'));
    swap(Repository::class, $brokenCache);

    getJson('/api/search?q=phone')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Phone')
        ->assertJsonPath('meta.total', 1);
});

it('caches search responses via cache repository and records took_ms from elasticsearch and 0 on cache hit', function (): void {
    Queue::fake([PublishSearchEvent::class]);

    $raw = [
        'took' => 17,
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

    $index = Mockery::mock(ProductIndex::class);
    $index->shouldReceive('search')->once()->andReturn($raw);
    swap(ProductIndex::class, $index);

    getJson('/api/search?q=phone')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Phone')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('facets.in_stock.true', 1);

    getJson('/api/search?q=phone')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Phone');

    Queue::assertPushed(PublishSearchEvent::class, 2);
    Queue::assertPushed(
        PublishSearchEvent::class,
        fn (PublishSearchEvent $job): bool => $job->tries === 8
            && $job->payload['query'] === 'phone'
            && $job->payload['took_ms'] === 17,
    );
    Queue::assertPushed(
        PublishSearchEvent::class,
        fn (PublishSearchEvent $job): bool => $job->payload['query'] === 'phone'
            && $job->payload['took_ms'] === 0,
    );
});

it('disables swagger ui outside local environment and preserves public routes and commands', function (): void {
    getJson('/api/documentation')->assertNotFound();
    get('/up')->assertOk();

    expect(route('api.v1.search', absolute: false))->toBe('/api/search');

    $names = array_keys(Artisan::all());
    expect($names)->toContain(
        'search:consume-catalog-events',
        'search:setup-index',
        'search:reindex',
        'search:replay-dlq',
    );
});
