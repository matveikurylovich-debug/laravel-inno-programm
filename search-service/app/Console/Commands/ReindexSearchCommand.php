<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ProductIndex;
use App\Services\Search\ProductDocument;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Override;
use Throwable;

class ReindexSearchCommand extends Command
{
    #[Override]
    protected $signature = 'search:reindex';

    #[Override]
    protected $description = 'Собирает новый индекс из каталога и атомарно переключает алиас';

    public function handle(ProductIndex $index, ProductDocument $documents, Factory $http, Repository $config): int
    {
        $pages = $this->pages($http, $config);
        if ($pages === null) {
            return self::FAILURE;
        }

        $index->rebuild($this->documentsStream($pages, $documents));
        $this->info('Алиас '.$index->indexName().' переключён');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return Generator<int, array{id: string, document: array<string, mixed>}>
     */
    private function documentsStream(array $pages, ProductDocument $documents): Generator
    {
        foreach ($pages as $product) {
            $rawId = $product['id'] ?? $product['product_id'] ?? '';
            yield [
                'id' => is_scalar($rawId) ? (string) $rawId : '',
                'document' => $documents->fromProductEvent($product),
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function pages(Factory $http, Repository $config): ?array
    {
        $rawBase = $config->get('services.catalog.url', '');
        $base = rtrim(is_scalar($rawBase) ? (string) $rawBase : '', '/');
        if ($base === '') {
            $this->error('CATALOG_URL не задан');

            return null;
        }

        try {
            $stores = $http->baseUrl($base)->timeout(5)->get('/v1/stores')->throw()->json('data');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return null;
        }

        if (! is_array($stores)) {
            return [];
        }

        $products = [];
        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }
            $storeId = is_numeric($store['id'] ?? null) ? (int) $store['id'] : 0;
            if ($storeId === 0) {
                continue;
            }

            $page = 1;
            do {
                try {
                    $response = $http->baseUrl($base)->timeout(5)->get("/v1/stores/{$storeId}/products", [
                        'page' => $page,
                        'per_page' => 100,
                    ])->throw()->json();
                } catch (Throwable $exception) {
                    $this->error($exception->getMessage());

                    return null;
                }

                $responseArray = is_array($response) ? $response : [];
                $rawChunk = $responseArray['data'] ?? null;
                if (is_array($rawChunk)) {
                    foreach ($rawChunk as $item) {
                        if (is_array($item)) {
                            /** @var array<string, mixed> $item */
                            $products[] = $item;
                        }
                    }
                }

                $meta = is_array($responseArray['meta'] ?? null) ? $responseArray['meta'] : [];
                $lastPage = is_numeric($meta['last_page'] ?? null) ? (int) $meta['last_page'] : $page;
                $page++;
            } while ($page <= $lastPage);
        }

        return $products;
    }
}
