<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Stock;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('stock');
        $data['quantity'] = (int) ($this->record->stock?->quantity ?? 0);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['quantity']);
        $data['attributes'] = $data['attributes'] ?? [];

        return $data;
    }

    protected function afterSave(): void
    {
        Stock::query()->firstOrCreate(
            [
                'product_id' => $this->record->id,
                'store_id' => $this->record->store_id,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
            ],
        )->update([
            'quantity' => (int) ($this->data['quantity'] ?? 0),
        ]);
    }
}
