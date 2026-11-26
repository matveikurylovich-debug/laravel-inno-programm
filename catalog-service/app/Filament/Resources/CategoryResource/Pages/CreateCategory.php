<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateCategory extends CreateRecord
{
    #[Override]
    protected static string $resource = CategoryResource::class;
}
