<?php

namespace App\Kafka\Consumers;

use App\Contracts\ProductIndex;
use App\Search\ProductDocument;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Throwable;

class StockEventsConsumer
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly ProductDocument $documents,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $message->getBody();

        if (is_string($body)) {
            $body = json_decode($body, true);
        }

        if (is_object($body)) {
            $body = (array) $body;
        }

        if (! is_array($body)) {
            return;
        }

        $event = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        if ($event !== 'stock.changed') {
            return;
        }

        $id = (string) ($body['product_id'] ?? '');

        if ($id === '') {
            return;
        }

        try {
            $this->index->updateInStock($id, $this->documents->inStock($body));
        } catch (Throwable $exception) {
            Log::error('Ошибка обновления наличия: '.$exception->getMessage(), ['payload' => $body]);
        }
    }
}
