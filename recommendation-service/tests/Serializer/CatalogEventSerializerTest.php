<?php

namespace App\Tests\Serializer;

use App\Message\CatalogEventMessage;
use App\Serializer\CatalogEventSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

final class CatalogEventSerializerTest extends TestCase
{
    public function test_encode_does_not_republish_an_empty_body(): void
    {
        $serializer = new CatalogEventSerializer();

        $this->expectException(\LogicException::class);

        $serializer->encode(new Envelope(new CatalogEventMessage('product.created', ['id' => 1])));
    }
}
