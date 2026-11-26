<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Message\CatalogEventMessage;
use JsonException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[Autoconfigure(public: true)]
final class CatalogEventSerializer implements SerializerInterface
{
    /**
     * @param array<string, mixed> $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? '';
        if (!\is_string($body) || '' === $body) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Пустое тело события каталога.');
        }

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Событие каталога не является JSON.', previous: $exception);
        }

        if (!\is_array($decoded)) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'Событие каталога должно быть JSON-объектом.');
        }

        $eventType = $decoded['event'] ?? $decoded['event_type'] ?? null;
        if (!\is_string($eventType) || '' === $eventType) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, 'В событии каталога нет поля event.');
        }

        $rawPayload = isset($decoded['data']) && \is_array($decoded['data']) ? $decoded['data'] : $decoded;
        $payload = [];
        foreach ($rawPayload as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }
        if (isset($decoded['event_type']) && !isset($payload['event'])) {
            $payload['event'] = $decoded['event_type'];
        }

        return new Envelope(new CatalogEventMessage($eventType, $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(Envelope $envelope): array
    {
        throw new LogicException(self::class.' только читает catalog.events');
    }
}
