<?php

declare(strict_types=1);

return [
    'catalog' => [
        'url' => (string) env('CATALOG_URL', 'http://catalog-nginx/api'),
    ],

    'analytics_broker' => [
        'url' => env('ANALYTICS_RABBITMQ_URL'),
        'exchange' => (string) env('ANALYTICS_RABBITMQ_EXCHANGE', 'analytics.events'),
    ],

    'search_events' => [
        'connection' => (string) env('SEARCH_EVENTS_QUEUE_CONNECTION', 'redis'),
        'queue' => (string) env('SEARCH_EVENTS_QUEUE', 'search-events'),
    ],
];
