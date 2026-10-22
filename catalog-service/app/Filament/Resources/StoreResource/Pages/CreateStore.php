<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : Str::slug((string) $data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}
