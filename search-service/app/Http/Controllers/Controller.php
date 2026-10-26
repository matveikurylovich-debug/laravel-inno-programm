<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Search Service API',
    description: 'Поиск товаров в Elasticsearch'
)]
#[OA\Server(url: '/', description: 'Search Service')]
abstract class Controller
{
    //
}
