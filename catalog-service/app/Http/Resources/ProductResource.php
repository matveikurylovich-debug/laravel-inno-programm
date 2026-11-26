<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'attributes' => $this->resource instanceof Product ? ($this->resource->getAttribute('attributes') ?? []) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'available_stock' => $this->available_stock,
            'total_available' => $this->available_stock,
            'primary_image' => $this->whenLoaded('primaryImage', function (): ?ProductImageResource {
                if ($this->primaryImage === null) {
                    return null;
                }

                return new ProductImageResource($this->primaryImage);
            }),
            'images' => $this->whenLoaded('images', fn () => ProductImageResource::collection($this->images)),
            'stock' => $this->whenLoaded('stock', fn () => StockResource::collection($this->stock)),
            'category' => $this->whenLoaded('category', fn (): CategoryResource => new CategoryResource($this->category)),
            'store' => $this->whenLoaded('store', fn (): StoreResource => new StoreResource($this->store)),
            'locations' => $this->when(
                $this->resource instanceof Product && array_key_exists('locations', $this->resource->getAttributes()),
                fn () => $this->resource instanceof Product ? $this->resource->getAttribute('locations') : [],
            ),
        ];
    }
}
