<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\CatalogEventMessage;
use App\Repository\ProductProjectionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class CategoryCatalog
{
    private const string CACHE_KEY = 'recommendation.store_categories';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
        private ProductProjectionRepository $products,
    ) {
    }

    /**
     * @return list<string>
     */
    public function names(int $storeId): array
    {
        $cached = $this->read()[$storeId] ?? [];

        return $this->unique(array_merge($cached, $this->products->distinctCategoryNames($storeId)));
    }

    public function refresh(CatalogEventMessage $message): void
    {
        if (!\in_array($message->eventType, ['category.created', 'category.updated', 'category.deleted'], true)) {
            return;
        }

        $payload = isset($message->payload['data']) && \is_array($message->payload['data'])
            ? $message->payload['data']
            : $message->payload;

        $rawStoreId = $payload['store_id'] ?? $message->payload['store_id'] ?? 0;
        $storeId = is_numeric($rawStoreId) ? (int) $rawStoreId : 0;
        if ($storeId <= 0) {
            return;
        }

        $names = array_merge(
            $this->read()[$storeId] ?? [],
            $this->products->distinctCategoryNames($storeId),
        );
        $oldName = $this->previousName($payload);
        if (null !== $oldName) {
            $names = array_values(array_filter(
                $names,
                static fn (string $item): bool => mb_strtolower($item) !== mb_strtolower($oldName),
            ));
        }
        $name = $this->payloadName($payload);
        if ('category.deleted' === $message->eventType) {
            if (null !== $name) {
                $names = array_values(array_filter(
                    $names,
                    static fn (string $item): bool => mb_strtolower($item) !== mb_strtolower($name),
                ));
            }
        } elseif (null !== $name) {
            $names[] = $name;
        }

        $catalog = $this->read();
        $catalog[$storeId] = $this->unique($names);
        $this->write($catalog);
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private function previousName(array $payload): ?string
    {
        foreach (['old_category_name', 'old_name', 'previous_name'] as $key) {
            $value = $payload[$key] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private function payloadName(array $payload): ?string
    {
        foreach (['category_name', 'name'] as $key) {
            $value = $payload[$key] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array<int, list<string>>
     */
    private function read(): array
    {
        $stored = $this->cache->get(self::CACHE_KEY, $this->emptyCatalog(...));
        if (!\is_array($stored)) {
            return [];
        }

        $catalog = [];
        foreach ($stored as $storeId => $names) {
            if (!\is_array($names)) {
                continue;
            }
            $catalog[(int) $storeId] = $this->strings($names);
        }

        return $catalog;
    }

    private function emptyCatalog(): mixed
    {
        return [];
    }

    /**
     * @param array<int, list<string>> $catalog
     */
    private function write(array $catalog): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, static fn (): array => $catalog);
    }

    /**
     * @param array<int|string, mixed> $names
     *
     * @return list<string>
     */
    private function strings(array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            if (\is_string($name)) {
                $values[] = $name;
            }
        }

        return $values;
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function unique(array $names): array
    {
        $unique = [];
        foreach ($names as $name) {
            $trimmed = trim($name);
            if ('' === $trimmed) {
                continue;
            }
            $unique[mb_strtolower($trimmed)] = $trimmed;
        }

        $values = array_values($unique);
        sort($values, \SORT_STRING);

        return $values;
    }
}
