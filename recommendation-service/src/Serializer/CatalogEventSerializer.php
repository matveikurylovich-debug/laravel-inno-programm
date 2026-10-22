<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Message\CatalogEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class CatalogEventSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? '';
        if (!is_string($body) || $body === '') {
            throw new MessageDecodingFailedException('Пустое тело события каталога.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MessageDecodingFailedException('Событие каталога не является JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new MessageDecodingFailedException('Событие каталога должно быть JSON-объектом.');
        }

        $eventType = $decoded['event'] ?? $decoded['event_type'] ?? null;
        if (!is_string($eventType) || $eventType === '') {
            throw new MessageDecodingFailedException('В событии каталога нет поля event.');
        }

        $payload = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;

        return new Envelope(new CatalogEventMessage($eventType, $payload));
    }

    public function encode(Envelope $envelope): array
    {
        return ['body' => '', 'headers' => []];
    }
}
