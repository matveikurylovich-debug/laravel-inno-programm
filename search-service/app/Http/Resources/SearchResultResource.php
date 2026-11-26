<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class SearchResultResource extends JsonResource
{
    #[Override]
    public static $wrap;

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{data?: list<array<string, mixed>>, meta?: array{total: int, page: int, per_page: int}, facets?: array<string, mixed>} $result */
        $result = is_array($this->resource) ? $this->resource : [];

        return [
            'data' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? [
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
            ],
            'facets' => $result['facets'] ?? [],
        ];
    }
}
