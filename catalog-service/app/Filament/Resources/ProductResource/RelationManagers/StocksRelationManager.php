<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StoreLocation;
use App\Services\Stock\StockAvailability;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Override;

class StocksRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'stock';

    #[Override]
    protected static ?string $title = 'Остатки по точкам';

    private StockAvailability $stockAvailability;

    public function boot(StockAvailability $stockAvailability): void
    {
        $this->stockAvailability = $stockAvailability;
    }

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
                            ->mapWithKeys(static fn (StoreLocation $location): array => [
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
                    ->required()
                    ->rule(static fn (?Stock $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if ($record instanceof Stock && is_numeric($value) && (int) $value < (int) $record->reserved_quantity) {
                            $fail('Остаток не может быть меньше резерва');
                        }
                    }),
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
                    ->state(static fn (Stock $record): int => max(0, $record->quantity - $record->reserved_quantity))
                    ->color(static fn (int $state): string => match (true) {
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

                        $quantity = is_numeric($data['quantity'] ?? null) ? (int) $data['quantity'] : 0;

                        return DB::transaction(function () use ($product, $location, $quantity): Stock {
                            $stock = Stock::query()->updateOrCreate(
                                [
                                    'product_id' => $product->id,
                                    'location_id' => $location->id,
                                ],
                                [
                                    'store_id' => $product->store_id,
                                    'quantity' => $quantity,
                                ],
                            );
                            $this->stockAvailability->publish((int) $product->id);

                            return $stock;
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Stock $record, array $data): Stock {
                        $quantity = is_numeric($data['quantity'] ?? null) ? (int) $data['quantity'] : 0;

                        DB::transaction(function () use ($record, $quantity): void {
                            $record->update([
                                'quantity' => $quantity,
                            ]);
                            $this->stockAvailability->publish((int) $record->product_id);
                        });

                        return $record;
                    }),
            ]);
    }
}
