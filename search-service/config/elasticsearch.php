<?php

return [
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ELASTICSEARCH_HOSTS', 'http://elasticsearch:9200')),
    ))),

    'index' => env('ELASTICSEARCH_INDEX', 'products'),

    'timeout' => (float) env('ELASTICSEARCH_TIMEOUT', 2),

    'connect_timeout' => (float) env('ELASTICSEARCH_CONNECT_TIMEOUT', 1),
];
