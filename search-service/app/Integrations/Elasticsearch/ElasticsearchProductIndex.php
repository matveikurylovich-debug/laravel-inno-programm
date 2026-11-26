<?php

declare(strict_types=1);

namespace App\Integrations\Elasticsearch;

use App\Contracts\ProductIndex;
use App\Exceptions\SearchFailedException;
use App\Exceptions\SearchUnavailableException;
use App\Services\Search\IndexAliasPlan;
use DateTimeImmutable;
use DateTimeZone;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Container\Attributes\Config;
use RuntimeException;

class ElasticsearchProductIndex implements ProductIndex
{
    public function __construct(
        private readonly Client $client,
        #[Config('elasticsearch.index', 'products')]
        private readonly string $index = 'products',
        private readonly ?string $schemaPath = null,
    ) {}

    public function indexName(): string
    {
        return $this->index;
    }

    /**
     * @return array<string, mixed>
     */
    public function indexDefinition(): array
    {
        $path = $this->schemaPath ?? dirname(__DIR__, 3).'/resources/elasticsearch/products.json';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Не найден файл схемы индекса: {$path}");
        }

        /** @var array<string, mixed> $definition */
        $definition = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $definition;
    }

    public function upsert(string $id, array $document): void
    {
        $this->client->index([
            'index' => $this->indexName(),
            'id' => $id,
            'body' => $document,
        ]);
    }

    public function delete(string $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->indexName(),
                'id' => $id,
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    public function updateInStock(string $id, bool $inStock): void
    {
        try {
            $this->client->update([
                'index' => $this->indexName(),
                'id' => $id,
                'body' => [
                    'doc' => ['is_in_stock' => $inStock],
                ],
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    public function renameCategory(int $categoryId, string $name): void
    {
        $this->client->updateByQuery([
            'index' => $this->indexName(),
            'conflicts' => 'proceed',
            'body' => [
                'query' => ['term' => ['category_id' => $categoryId]],
                'script' => [
                    'source' => 'ctx._source.category_name = params.name',
                    'params' => ['name' => $name],
                ],
            ],
        ]);
    }

    public function clearCategory(int $categoryId): void
    {
        $this->client->updateByQuery([
            'index' => $this->indexName(),
            'conflicts' => 'proceed',
            'body' => [
                'query' => ['term' => ['category_id' => $categoryId]],
                'script' => [
                    'source' => 'ctx._source.category_id = null; ctx._source.category_path = []; ctx._source.category_name = params.name',
                    'params' => ['name' => ''],
                ],
            ],
        ]);
    }

    public function search(array $query): array
    {
        try {
            /** @var Elasticsearch $response */
            $response = $this->client->search([
                'index' => $this->indexName(),
                'body' => $query,
            ]);
        } catch (NoNodeAvailableException|ServerResponseException $exception) {
            throw new SearchUnavailableException('Поиск недоступен', previous: $exception);
        } catch (ClientResponseException $exception) {
            throw new SearchFailedException('Ошибка поиска', previous: $exception);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->asArray();

        return $payload;
    }

    public function setup(bool $force = false): void
    {
        if ($force) {
            // Боевой индекс не удаляется. Полная пересборка — search:reindex.
        }

        $alias = $this->indexName();
        /** @var Elasticsearch $aliasCheck */
        $aliasCheck = $this->client->indices()->existsAlias(['name' => $alias]);
        /** @var Elasticsearch $indexCheck */
        $indexCheck = $this->client->indices()->exists(['index' => $alias]);

        if ($aliasCheck->asBool() || $indexCheck->asBool()) {
            return;
        }

        $index = $this->versionedIndexName($alias);
        $this->createIndex($index);
        $this->client->indices()->updateAliases([
            'body' => ['actions' => IndexAliasPlan::actions($alias, $index, false, [], false)],
        ]);
    }

    /**
     * @param  iterable<int, array{id: string, document: array<string, mixed>}>  $documents
     */
    public function rebuild(iterable $documents): void
    {
        $alias = $this->indexName();
        $index = $this->versionedIndexName($alias);
        $this->createIndex($index);

        $batch = [];
        foreach ($documents as $document) {
            $batch[] = $document;
            if (count($batch) === 500) {
                $this->bulk($index, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->bulk($index, $batch);
        }
        $this->client->indices()->refresh(['index' => $index]);

        /** @var Elasticsearch $aliasCheck */
        $aliasCheck = $this->client->indices()->existsAlias(['name' => $alias]);
        $aliasExists = $aliasCheck->asBool();

        $current = [];
        if ($aliasExists) {
            /** @var Elasticsearch $aliasResponse */
            $aliasResponse = $this->client->indices()->getAlias(['name' => $alias]);
            $current = array_map(strval(...), array_keys($aliasResponse->asArray()));
        }

        $concrete = false;
        if (! $aliasExists) {
            /** @var Elasticsearch $indexCheck */
            $indexCheck = $this->client->indices()->exists(['index' => $alias]);
            $concrete = $indexCheck->asBool();
        }

        $this->client->indices()->updateAliases([
            'body' => ['actions' => IndexAliasPlan::actions($alias, $index, $aliasExists, $current, $concrete)],
        ]);
    }

    /**
     * @param  list<array{id: string, document: array<string, mixed>}>  $documents
     */
    private function bulk(string $index, array $documents): void
    {
        $body = [];
        foreach ($documents as $document) {
            $body[] = ['index' => ['_index' => $index, '_id' => $document['id']]];
            $body[] = $document['document'];
        }
        $this->client->bulk(['body' => $body]);
    }

    private function createIndex(string $index): void
    {
        $this->client->indices()->create([
            'index' => $index,
            'body' => $this->indexDefinition(),
        ]);
    }

    private function versionedIndexName(string $alias): string
    {
        return $alias.'_'.new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('YmdHisu');
    }
}
