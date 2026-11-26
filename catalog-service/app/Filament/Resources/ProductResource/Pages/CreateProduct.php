<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\Products\CreateProduct as CreateProductService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class CreateProduct extends CreateRecord
{
    #[Override]
    protected static string $resource = ProductResource::class;

    private CreateProductService $createProduct;

    public function boot(CreateProductService $createProduct): void
    {
        $this->createProduct = $createProduct;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->createProduct->handle($data);
    }
}
