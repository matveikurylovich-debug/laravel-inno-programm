<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\SearchProductsRequest;
use App\Http\Resources\SearchResultResource;
use App\Services\Search\SearchService;

class SearchController extends Controller
{
    public function __invoke(SearchProductsRequest $request, SearchService $search): SearchResultResource
    {
        return new SearchResultResource($search->search($request->criteria()));
    }
}
