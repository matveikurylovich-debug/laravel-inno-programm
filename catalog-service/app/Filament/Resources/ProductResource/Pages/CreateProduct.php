<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\Stock;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug((string) $data['name']);
        $data['sku'] = filled($data['sku'] ?? null)
            ? $data['sku']
            : Str::upper(Str::slug((string) $data['name'], '')).'-'.Str::upper(Str::random(4));
        $data['attributes'] = $data['attributes'] ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $quantity = (int) ($data['quantity'] ?? 0);
        unset($data['quantity']);

        return DB::transaction(function () use ($data, $quantity): Product {
            /** @var Product $product */
            $product = Product::query()->create($data);

            Stock::query()->create([
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);

            return $product->fresh(['stock']) ?? $product;
        });
    }
}
