<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'all_children' => $this->whenLoaded('allChildren', fn () => CategoryResource::collection($this->allChildren)),
            'children' => $this->whenLoaded('children', fn () => CategoryResource::collection($this->children)),
            'parent' => $this->whenLoaded('parent', fn (): CategoryResource => new CategoryResource($this->parent)),
        ];
    }
}
