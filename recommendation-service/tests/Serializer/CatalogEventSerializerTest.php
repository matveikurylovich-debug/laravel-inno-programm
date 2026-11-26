<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Message\CatalogEventMessage;
use App\Serializer\CatalogEventSerializer;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

final class CatalogEventSerializerTest extends TestCase
{
    public function testEncodeDoesNotRepublishAnEmptyBody(): void
    {
        $serializer = new CatalogEventSerializer();

        $this->expectException(LogicException::class);

        $serializer->encode(new Envelope(new CatalogEventMessage('product.created', ['id' => 1])));
    }

    public function testInvalidPayloadsReturnFailedEnvelopeInsteadOfThrowing(): void
    {
        $serializer = new CatalogEventSerializer();

        foreach ([
            ['body' => ''],
            ['body' => '{invalid-json'],
            ['body' => '"not-an-object"'],
            ['body' => '{"id": 10}'],
        ] as $encoded) {
            $envelope = $serializer->decode($encoded);
            $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        }
    }

    public function testDecodesGoldenCatalogContractFixtures(): void
    {
        $serializer = new CatalogEventSerializer();

        $createdRaw = (string) file_get_contents(\dirname(__DIR__, 3).'/contracts/catalog.events/product.created.json');
        $createdEnvelope = $serializer->decode(['body' => $createdRaw]);
        $created = $createdEnvelope->getMessage();

        $this->assertInstanceOf(CatalogEventMessage::class, $created);
        $this->assertSame('product.created', $created->eventType);
        $this->assertSame(10, $created->getProductId());
        $this->assertSame('Смартфон X', $created->payload['name']);

        $stockRaw = (string) file_get_contents(\dirname(__DIR__, 3).'/contracts/catalog.events/stock.changed.json');
        $stockEnvelope = $serializer->decode(['body' => $stockRaw]);
        $stock = $stockEnvelope->getMessage();

        $this->assertInstanceOf(CatalogEventMessage::class, $stock);
        $this->assertSame('stock.changed', $stock->eventType);
        $this->assertSame(10, $stock->getProductId());
        $this->assertSame(3, $stock->payload['available']);
    }

    public function testBackfillsEventTypeFromEnvelopeWhenInnerEventIsOmitted(): void
    {
        $serializer = new CatalogEventSerializer();
        $envelope = $serializer->decode([
            'body' => json_encode([
                'event_type' => 'product.deleted',
                'data' => ['product_id' => 77],
            ], \JSON_THROW_ON_ERROR),
        ]);

        $message = $envelope->getMessage();
        $this->assertInstanceOf(CatalogEventMessage::class, $message);
        $this->assertSame('product.deleted', $message->eventType);
        $this->assertSame('product.deleted', $message->payload['event']);
        $this->assertSame(77, $message->getProductId());
    }
}
