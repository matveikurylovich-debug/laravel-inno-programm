<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\StocksRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Rules\ProductRules;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ProductResource extends Resource
{
    #[Override]
    protected static ?string $model = Product::class;

    #[Override]
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    #[Override]
    protected static ?string $navigationLabel = 'Товары';

    #[Override]
    protected static ?string $modelLabel = 'товар';

    #[Override]
    protected static ?string $pluralModelLabel = 'Товары';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->disabledOn('edit')
                    ->live()
                    ->label('Магазин'),
                Forms\Components\Select::make('category_id')
                    ->label('Категория')
                    ->searchable()
                    ->preload()
                    ->options(static function (Get $get, ?Product $record): array {
                        $storeId = self::resolveStoreId($get, $record);
                        if ($storeId === null) {
                            return [];
                        }

                        /** @var array<int|string, string> $options */
                        $options = Category::query()
                            ->where('store_id', $storeId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();

                        return $options;
                    })
                    ->rules(static fn (Get $get, ?Product $record): array => ProductRules::category(
                        self::resolveStoreId($get, $record),
                        required: false,
                    )),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Название'),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₽')
                    ->label('Цена'),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->visibleOn('create')
                    ->label('Начальный остаток первой точки'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required()
                    ->label('В продаже'),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->hiddenOn('create')
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->rules(static fn (Get $get, ?Product $record): array => ProductRules::sku(
                        self::resolveStoreId($get, $record),
                        $record instanceof Product ? (int) $record->id : null,
                    ))
                    ->helperText('Пустое значение сохранится без изменений.'),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->hiddenOn('create')
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->rules(static fn (Get $get, ?Product $record): array => ProductRules::slug(
                        self::resolveStoreId($get, $record),
                        $record instanceof Product ? (int) $record->id : null,
                    )),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull()
                    ->label('Описание'),
                Forms\Components\KeyValue::make('attributes')
                    ->keyLabel('Ключ')
                    ->valueLabel('Значение')
                    ->rules(ProductRules::attributes())
                    ->columnSpanFull()
                    ->label('Характеристики'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->sortable()
                    ->label('Магазин'),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->label('Категория'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Название'),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price')
                    ->money('RUB')
                    ->sortable()
                    ->label('Цена'),
                Tables\Columns\TextColumn::make('available_stock')
                    ->numeric()
                    ->label('Доступно'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('В продаже'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Магазин'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Категория'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return list<class-string>
     */
    public static function getRelations(): array
    {
        return [
            StocksRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    /**
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Product> $query */
        $query = parent::getEloquentQuery()->with(['store', 'category', 'stock']);

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    private static function resolveStoreId(Get $get, ?Product $record): ?int
    {
        if ($record instanceof Product) {
            return (int) $record->store_id;
        }

        $raw = $get('store_id');

        return is_numeric($raw) ? (int) $raw : null;
    }
}
