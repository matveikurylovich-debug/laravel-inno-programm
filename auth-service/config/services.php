<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gateway' => [
        'catalog' => env('CATALOG_SERVICE_URL', 'https://catalog-nginx/api'),
        'order' => env('ORDER_SERVICE_URL', 'https://order-nginx/api'),
        'notifications' => env('NOTIFICATION_SERVICE_URL', 'https://notification_nginx/api'),
        'search' => env('SEARCH_SERVICE_URL', 'https://search-nginx/api'),
        'recommendations' => env('RECOMMENDATION_SERVICE_URL', 'https://recommendation-nginx'),
        'analytics' => env('ANALYTICS_SERVICE_URL', 'https://analytics-nginx/analytics'),
    ],

    'analytics_broker' => [
        'url' => env('ANALYTICS_RABBITMQ_URL'),
        'exchange' => env('ANALYTICS_RABBITMQ_EXCHANGE', 'analytics.events'),
    ],

];
