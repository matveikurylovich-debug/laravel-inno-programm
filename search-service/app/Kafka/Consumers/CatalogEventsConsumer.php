<?php

namespace App\Kafka\Consumers;

use App\Contracts\ProductIndex;
use App\Search\ProductDocument;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Throwable;

class CatalogEventsConsumer
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly ProductDocument $documents,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $this->body($message);
        $event = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        try {
            match ($event) {
                'product.created', 'product.updated' => $this->upsert($body),
                'product.deleted' => $this->delete($body),
                'category.updated' => $this->renameCategory($body),
                'stock.changed' => $this->stock($body),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::error("Ошибка индексации события {$event}: ".$exception->getMessage(), [
                'payload' => $body,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(array $payload): void
    {
        $id = (string) ($payload['product_id'] ?? $payload['id'] ?? '');

        if ($id === '') {
            return;
        }

        $this->index->upsert($id, $this->documents->fromProductEvent($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function delete(array $payload): void
    {
        $id = (string) ($payload['product_id'] ?? $payload['id'] ?? '');

        if ($id === '') {
            return;
        }

        $this->index->delete($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renameCategory(array $payload): void
    {
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $name = (string) ($payload['category_name'] ?? $payload['name'] ?? '');

        if ($categoryId === 0 || $name === '') {
            return;
        }

        $this->index->renameCategory($categoryId, $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stock(array $payload): void
    {
        $id = (string) ($payload['product_id'] ?? '');

        if ($id === '') {
            return;
        }

        $this->index->updateInStock($id, $this->documents->inStock($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ConsumerMessage $message): array
    {
        $body = $message->getBody();

        if (is_string($body)) {
            $body = json_decode($body, true);
        }

        if (is_object($body)) {
            $body = (array) $body;
        }

        return is_array($body) ? $body : [];
    }
}
