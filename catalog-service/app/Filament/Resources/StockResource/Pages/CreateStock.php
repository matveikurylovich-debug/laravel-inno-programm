<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockResource\Pages;

use App\Filament\Resources\StockResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateStock extends CreateRecord
{
    #[Override]
    protected static string $resource = StockResource::class;
}
