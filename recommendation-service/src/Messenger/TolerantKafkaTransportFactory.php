<?php

declare(strict_types=1);

namespace App\Messenger;

use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Ufo\KafkaMessenger\Transport\KafkaTransportFactory;

/**
 * Symfony passes transport_name to every factory. The Kafka factory rejects that option.
 *
 * @implements TransportFactoryInterface<TransportInterface>
 */
#[AutoconfigureTag('messenger.transport_factory', ['priority' => 10])]
final readonly class TolerantKafkaTransportFactory implements TransportFactoryInterface
{
    public function __construct(private KafkaTransportFactory $inner)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(#[SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name']);

        return $this->inner->createTransport($dsn, $options, $serializer);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(#[SensitiveParameter] string $dsn, array $options): bool
    {
        return $this->inner->supports($dsn, $options);
    }
}
