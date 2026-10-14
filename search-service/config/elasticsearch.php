<?php

return [
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ELASTICSEARCH_HOSTS', 'http://elasticsearch:9200')),
    ))),

    'index' => env('ELASTICSEARCH_INDEX', 'products'),
];
