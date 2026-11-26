<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Tests;

use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use PHPUnit\Framework\TestCase;

final class EventEnvelopeTest extends TestCase
{
    public function test_wrap_matches_the_contract_and_flatten_reads_both_shapes(): void
    {
        $envelope = EventEnvelope::wrap('order.paid', 'order-service', [
            'event' => 'order.paid',
            'order_id' => '1',
        ], 'event-1', '2026-11-25T12:00:00+00:00');

        $this->assertSame(
            ['event_id', 'event_type', 'schema_version', 'occurred_at', 'producer', 'data'],
            array_keys($envelope->toArray()),
        );
        $this->assertSame('order.paid', EventEnvelope::flatten($envelope->toArray())['event']);
        $this->assertSame('legacy', EventEnvelope::flatten(['event' => 'legacy'])['event']);
    }
}
