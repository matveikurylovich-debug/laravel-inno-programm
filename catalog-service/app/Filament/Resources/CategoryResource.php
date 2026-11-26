<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Override;

class CategoryResource extends Resource
{
    #[Override]
    protected static ?string $model = Category::class;

    #[Override]
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    #[Override]
    protected static ?string $navigationLabel = 'Категории';

    #[Override]
    protected static ?string $modelLabel = 'категория';

    #[Override]
    protected static ?string $pluralModelLabel = 'Категории';

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
                Forms\Components\Select::make('parent_id')
                    ->label('Родитель')
                    ->searchable()
                    ->preload()
                    ->options(static function (Get $get, ?Category $record): array {
                        $storeId = self::resolveStoreId($get, $record);
                        if ($storeId === null) {
                            return [];
                        }

                        $excludedIds = $record instanceof Category
                            ? Category::idsIncludingDescendants($storeId, (int) $record->id)
                            : [];

                        /** @var array<int|string, string> $options */
                        $options = Category::query()
                            ->where('store_id', $storeId)
                            ->when($excludedIds !== [], static fn (Builder $query): Builder => $query->whereNotIn('id', $excludedIds))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();

                        return $options;
                    })
                    ->rule(static fn (Get $get, ?Category $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if ($value === null || $value === '' || ! is_numeric($value)) {
                            return;
                        }

                        $storeId = self::resolveStoreId($get, $record);
                        if ($storeId === null) {
                            return;
                        }

                        $parentId = (int) $value;
                        $parent = Category::query()->find($parentId);

                        if ($parent === null || (int) $parent->store_id !== $storeId) {
                            $fail('Родительская категория принадлежит другому магазину');

                            return;
                        }

                        if ($record instanceof Category && in_array($parentId, Category::idsIncludingDescendants($storeId, (int) $record->id), true)) {
                            $fail('Нельзя выбрать текущую категорию или её потомка в качестве родителя');
                        }
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Название'),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->hiddenOn('create')
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->rules(static function (Get $get, ?Category $record): array {
                        $storeId = self::resolveStoreId($get, $record);
                        if ($storeId === null) {
                            return ['nullable', 'string', 'max:255'];
                        }

                        $unique = Rule::unique('categories', 'slug')->where('store_id', $storeId);
                        if ($record instanceof Category) {
                            $unique = $unique->ignore($record->id);
                        }

                        return ['nullable', 'string', 'max:255', $unique];
                    }),
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
                    ->description(static fn (Category $record): string => $record->parent?->name
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

    /**
     * @return Builder<Category>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Category> $query */
        $query = parent::getEloquentQuery()->with(['store', 'parent']);

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    private static function resolveStoreId(Get $get, ?Category $record): ?int
    {
        if ($record instanceof Category) {
            return (int) $record->store_id;
        }

        $raw = $get('store_id');

        return is_numeric($raw) ? (int) $raw : null;
    }
}
