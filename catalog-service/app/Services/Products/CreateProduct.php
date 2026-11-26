<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StoreLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct
{
    public function __construct(
        private readonly ProductEventPublisher $productEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Product
    {
        $rawQuantity = $data['quantity'] ?? 0;
        $quantity = is_numeric($rawQuantity) ? (int) $rawQuantity : 0;
        unset($data['quantity']);

        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $sku = $data['sku'] ?? null;
        $data['sku'] = is_string($sku) && trim($sku) !== ''
            ? $sku
            : Str::upper(Str::slug($name, '')).'-'.Str::upper(Str::random(4));

        if (! isset($data['slug']) || ! is_string($data['slug']) || trim($data['slug']) === '') {
            unset($data['slug']);
        }

        $data['attributes'] = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return DB::transaction(function () use ($data, $quantity): Product {
            /** @var Product $product */
            $product = Product::query()->create($data);

            $locationId = StoreLocation::query()
                ->where('store_id', $product->store_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            $locationId ??= StoreLocation::query()->create([
                'store_id' => $product->store_id,
                'name' => 'Основная',
                'city' => 'Минск',
                'address' => 'Не указан',
                'working_hours' => '10:00 - 22:00',
                'is_active' => true,
            ])->id;

            Stock::query()->create([
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);

            $fresh = $product->fresh(['stock', 'category']) ?? $product;
            $this->productEvents->publishCreated($fresh);

            return $fresh;
        });
    }
}
