<?php

declare(strict_types=1);

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateStore extends CreateRecord
{
    #[Override]
    protected static string $resource = StoreResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] ??= true;

        return $data;
    }
}
