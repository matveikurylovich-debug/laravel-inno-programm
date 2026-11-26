<?php

declare(strict_types=1);

use App\Messaging\Consumers\AuthEventsConsumer;
use App\Messaging\Consumers\OrderEventsConsumer;

return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),

    'securityProtocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),

    'sasl' => [
        'mechanisms' => env('KAFKA_MECHANISMS', 'PLAINTEXT'),
        'username' => env('KAFKA_USERNAME'),
        'password' => env('KAFKA_PASSWORD'),
    ],

    'consumer_timeout_ms' => (int) env('KAFKA_CONSUMER_DEFAULT_TIMEOUT', 2000),

    'offset_reset' => env('KAFKA_OFFSET_RESET', 'earliest'),

    'auto_commit' => (bool) env('KAFKA_AUTO_COMMIT', false),

    'sleep_on_error' => (int) env('KAFKA_ERROR_SLEEP', 5),

    'partition' => (int) env('KAFKA_PARTITION', 0),

    'compression' => env('KAFKA_COMPRESSION_TYPE', 'snappy'),

    'debug' => (bool) env('KAFKA_DEBUG', false),

    'flush_retry_sleep_in_ms' => 100,

    'flush_retries' => 10,

    'flush_timeout_in_ms' => 1000,

    'cache_driver' => env('KAFKA_CACHE_DRIVER', env('CACHE_STORE', 'mongodb')),

    'message_id_key' => env('MESSAGE_ID_KEY', 'laravel-kafka::message-id'),

    'consumer_retries' => (int) env('KAFKA_CONSUMER_RETRIES', 3),

    'consumer_retry_backoff_ms' => (int) env('KAFKA_CONSUMER_RETRY_BACKOFF_MS', 1000),

    'topics' => [
        'auth_events' => env('KAFKA_TOPIC_AUTH_EVENTS', 'auth.events'),
        'order_events' => env('KAFKA_TOPIC_ORDER_EVENTS', 'order.events'),
    ],

    'consumers' => [
        'auth-events' => [
            'topics' => ['auth_events'],
            'group' => env('KAFKA_AUTH_CONSUMER_GROUP', 'notification-service.auth-events'),
            'handler' => AuthEventsConsumer::class,
            'dlq' => env('KAFKA_AUTH_DLQ_TOPIC', 'auth.events.dlq.notification-service'),
        ],
        'order-events' => [
            'topics' => ['order_events'],
            'group' => env('KAFKA_ORDER_CONSUMER_GROUP', 'notification-service.order-events'),
            'handler' => OrderEventsConsumer::class,
            'dlq' => env('KAFKA_ORDER_DLQ_TOPIC', 'order.events.dlq.notification-service'),
        ],
    ],
];
