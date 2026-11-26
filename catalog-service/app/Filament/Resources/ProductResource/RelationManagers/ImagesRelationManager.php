<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Products\ProductImageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Override;

class ImagesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'images';

    #[Override]
    protected static ?string $title = 'Фото';

    private ProductImageService $imageService;

    public function boot(ProductImageService $imageService): void
    {
        $this->imageService = $imageService;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('upload')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->required()
                    ->storeFiles(false)
                    ->label('Файл'),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Главное фото'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('Превью'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Главное'),
                Tables\Columns\TextColumn::make('position')
                    ->numeric()
                    ->label('Порядок'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): ProductImage {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        return $this->imageService->upload(
                            $product,
                            $this->uploadedFile($data['upload'] ?? null),
                            (bool) ($data['is_primary'] ?? false),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->using(function (ProductImage $record): void {
                        $this->imageService->delete($record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->label('Удалить')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(function (iterable $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof ProductImage) {
                                    $this->imageService->delete($record);
                                }
                            }
                        }),
                ]),
            ]);
    }

    private function uploadedFile(mixed $upload): UploadedFile
    {
        if ($upload instanceof TemporaryUploadedFile || $upload instanceof UploadedFile) {
            return $upload;
        }

        if (is_array($upload) && $upload !== []) {
            return $this->uploadedFile(reset($upload));
        }

        throw new InvalidArgumentException('Не удалось прочитать загруженный файл.');
    }
}
