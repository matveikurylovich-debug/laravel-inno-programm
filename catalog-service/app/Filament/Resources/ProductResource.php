<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\StocksRelationManager;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Товары';

    protected static ?string $modelLabel = 'товар';

    protected static ?string $pluralModelLabel = 'Товары';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->live()
                    ->label('Магазин'),
                Forms\Components\Select::make('category_id')
                    ->label('Категория')
                    ->searchable()
                    ->preload()
                    ->options(function (Get $get): array {
                        $storeId = $get('store_id');
                        if (! $storeId) {
                            return [];
                        }

                        return Category::query()
                            ->where('store_id', $storeId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    }),
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
                    ->helperText('Пустое значение сохранится без изменений.'),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->hiddenOn('create'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull()
                    ->label('Описание'),
                Forms\Components\KeyValue::make('attributes')
                    ->keyLabel('Ключ')
                    ->valueLabel('Значение')
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

    public static function getRelations(): array
    {
        return [
            StocksRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store', 'category', 'stock']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
