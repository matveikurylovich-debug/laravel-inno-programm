<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'категория';

    protected static ?string $pluralModelLabel = 'Категории';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('store_id')
                    ->relationship('store', 'name')
                    ->required()
                    ->live()
                    ->label('Магазин'),
                Forms\Components\Select::make('parent_id')
                    ->label('Родитель')
                    ->searchable()
                    ->preload()
                    ->options(function (Get $get, ?Category $record): array {
                        $storeId = $get('store_id') ?? $record?->store_id;
                        if (! $storeId) {
                            return [];
                        }

                        return Category::query()
                            ->where('store_id', $storeId)
                            ->when($record?->id, fn (Builder $query, int $id) => $query->whereKeyNot($id))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Название'),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->hiddenOn('create'),
                Forms\Components\TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->label('Порядок'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->sortable()
                    ->label('Магазин'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Category $record): string => $record->parent?->name
                        ? '← '.$record->parent->name
                        : 'Корень')
                    ->label('Название'),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->sortable()
                    ->label('Порядок'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->label('Магазин'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store', 'parent']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
