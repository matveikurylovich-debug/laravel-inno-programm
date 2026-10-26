<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StoreLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stock';

    protected static ?string $title = 'Остатки по точкам';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('location_id')
                    ->label('Точка')
                    ->options(function (): array {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        return StoreLocation::query()
                            ->where('store_id', $product->store_id)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (StoreLocation $location): array => [
                                $location->id => $location->name.' — '.$location->address,
                            ])
                            ->all();
                    })
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('quantity')
                    ->label('На полке')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label('В резерве')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Точка'),
                Tables\Columns\TextColumn::make('location.address')
                    ->label('Адрес'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('На полке')
                    ->numeric(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Резерв')
                    ->numeric(),
                Tables\Columns\TextColumn::make('available')
                    ->label('Доступно')
                    ->badge()
                    ->state(fn (Stock $record): int => max(0, $record->quantity - $record->reserved_quantity))
                    ->color(fn (int $state): string => match (true) {
                        $state > 5 => 'success',
                        $state > 0 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Stock {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        $location = StoreLocation::query()
                            ->whereKey($data['location_id'])
                            ->where('store_id', $product->store_id)
                            ->firstOrFail();

                        return Stock::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'location_id' => $location->id,
                            ],
                            [
                                'store_id' => $product->store_id,
                                'quantity' => (int) $data['quantity'],
                            ],
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Stock $record, array $data): Stock {
                        $record->update([
                            'quantity' => (int) $data['quantity'],
                        ]);

                        return $record;
                    }),
            ]);
    }
}
