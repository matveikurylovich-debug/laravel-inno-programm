<?php

declare(strict_types=1);

use App\Integrations\Elasticsearch\ElasticsearchProductIndex;
use App\Services\Search\ProductDocument;
use App\Services\Search\SearchQueryBuilder;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Support\Env;
use PHPUnit\Framework\Assert;

uses()->group('elasticsearch');

function elasticsearchIntegrationRequired(): bool
{
    $explicitFlag = filter_var(getenv('ELASTICSEARCH_INTEGRATION') ?: Env::get('ELASTICSEARCH_INTEGRATION', false), FILTER_VALIDATE_BOOLEAN);
    if ($explicitFlag) {
        return true;
    }

    $inCi = filter_var(getenv('CI') ?: false, FILTER_VALIDATE_BOOLEAN);
    $ciHosts = getenv('ELASTICSEARCH_HOSTS');

    return $inCi && is_string($ciHosts) && trim($ciHosts) !== '';
}

function cleanupIntegrationAlias(Client $client, string $alias): void
{
    try {
        /** @var Elasticsearch $aliasExists */
        $aliasExists = $client->indices()->existsAlias(['name' => $alias]);
        if ($aliasExists->asBool()) {
            /** @var Elasticsearch $aliasResponse */
            $aliasResponse = $client->indices()->getAlias(['name' => $alias]);
            foreach (array_keys($aliasResponse->asArray()) as $concrete) {
                $client->indices()->delete(['index' => (string) $concrete]);
            }
        }

        /** @var Elasticsearch $concreteExists */
        $concreteExists = $client->indices()->exists(['index' => $alias]);
        if ($concreteExists->asBool()) {
            $client->indices()->delete(['index' => $alias]);
        }
    } catch (Throwable) {
        // Ignore cleanup errors when Elasticsearch is unreachable.
    }
}

/**
 * @param  array<string, mixed>  $searchResult
 * @return list<string>
 */
function extractHitIds(array $searchResult): array
{
    $hitsWrapper = is_array($searchResult['hits'] ?? null) ? $searchResult['hits'] : [];
    $rawHits = is_array($hitsWrapper['hits'] ?? null) ? $hitsWrapper['hits'] : [];

    $ids = [];
    foreach ($rawHits as $hit) {
        if (! is_array($hit)) {
            continue;
        }
        $source = is_array($hit['_source'] ?? null) ? $hit['_source'] : [];
        $id = $source['id'] ?? '';
        if (is_scalar($id)) {
            $ids[] = (string) $id;
        }
    }

    return $ids;
}

it('creates index from resources/elasticsearch/products.json, matches synonyms, typos, prefixes, and swaps alias cleanly', function (): void {
    /** @var Client $client */
    $client = resolve(Client::class);

    $reachable = false;
    try {
        /** @var Elasticsearch $ping */
        $ping = $client->ping();
        $reachable = $ping->asBool();
    } catch (Throwable) {
        $reachable = false;
    }

    if (! elasticsearchIntegrationRequired() && ! $reachable) {
        Assert::markTestSkipped('Elasticsearch integration suite skipped (set ELASTICSEARCH_INTEGRATION=1 with a running Elasticsearch instance).');
    }

    $alias = 'products_it_'.bin2hex(random_bytes(4));

    try {
        $index = new ElasticsearchProductIndex($client, $alias);
        $documents = new ProductDocument;
        $builder = new SearchQueryBuilder;

        $index->setup();

        /** @var Elasticsearch $aliasCheck */
        $aliasCheck = $client->indices()->existsAlias(['name' => $alias]);
        expect($aliasCheck->asBool())->toBeTrue();

        /** @var Elasticsearch $aliasInfo */
        $aliasInfo = $client->indices()->getAlias(['name' => $alias]);
        $initialConcreteIndexes = array_map(strval(...), array_keys($aliasInfo->asArray()));
        expect($initialConcreteIndexes)->toHaveCount(1);
        $initialConcrete = $initialConcreteIndexes[0];

        $index->upsert('101', $documents->fromProductEvent([
            'product_id' => 101,
            'store_id' => 1,
            'name' => 'Мобильный телефон Galaxy',
            'description' => 'Яркий экран и быстрая зарядка',
            'price' => 59990,
            'available' => 10,
            'category_id' => 2,
            'category_path' => [1, 2],
            'category_name' => 'Электроника',
            'attributes' => ['color' => 'черный'],
        ]));

        $index->upsert('102', $documents->fromProductEvent([
            'product_id' => 102,
            'store_id' => 1,
            'name' => 'Смартчасы Pro',
            'description' => 'Водонепроницаемый корпус',
            'price' => 19990,
            'available' => 4,
            'category_id' => 3,
            'category_path' => [1, 3],
            'category_name' => 'Гаджеты',
        ]));

        $client->indices()->refresh(['index' => $alias]);

        $synonymHits = $index->search($builder->build(['q' => 'смартфон']));
        expect(extractHitIds($synonymHits))->toContain('101');

        $typoHits = $index->search($builder->build(['q' => 'тилефон']));
        expect(extractHitIds($typoHits))->toContain('101');

        $prefixHits = $index->search($builder->build(['q' => 'смарт']));
        expect(extractHitIds($prefixHits))->toContain('102');

        $index->rebuild([
            [
                'id' => '201',
                'document' => $documents->fromProductEvent([
                    'product_id' => 201,
                    'store_id' => 1,
                    'name' => 'Ноутбук Ultra',
                    'description' => 'Тонкий корпус',
                    'price' => 89990,
                    'available' => 3,
                    'category_id' => 4,
                    'category_path' => [1, 4],
                    'category_name' => 'Компьютеры',
                ]),
            ],
        ]);

        /** @var Elasticsearch $rebuiltAliasInfo */
        $rebuiltAliasInfo = $client->indices()->getAlias(['name' => $alias]);
        $rebuiltConcreteIndexes = array_map(strval(...), array_keys($rebuiltAliasInfo->asArray()));
        expect($rebuiltConcreteIndexes)->toHaveCount(1)
            ->and($rebuiltConcreteIndexes[0])->not->toBe($initialConcrete);

        /** @var Elasticsearch $oldConcreteExists */
        $oldConcreteExists = $client->indices()->exists(['index' => $initialConcrete]);
        expect($oldConcreteExists->asBool())->toBeFalse();

        $laptopHits = $index->search($builder->build(['q' => 'лэптоп']));
        expect(extractHitIds($laptopHits))->toBe(['201']);
    } finally {
        cleanupIntegrationAlias($client, $alias);
    }
});
