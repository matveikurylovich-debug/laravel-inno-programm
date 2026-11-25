<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SearchController extends Controller
{
    #[OA\Get(
        path: '/api/search',
        summary: 'Поиск товаров',
        tags: ['Search'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 200)),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'price_min', in: 'query', required: false, schema: new OA\Schema(type: 'number', minimum: 0)),
            new OA\Parameter(name: 'price_max', in: 'query', required: false, schema: new OA\Schema(type: 'number', minimum: 0)),
            new OA\Parameter(name: 'in_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['relevance', 'price_asc', 'price_desc'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Результаты поиска'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function __invoke(SearchRequest $request, SearchService $search): JsonResponse
    {
        return response()->json($search->search($request->validated()));
    }
}
