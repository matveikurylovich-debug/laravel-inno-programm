<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    protected string $disk = 'minio';

    /**
     * Загрузить изображение для товара
     */
    public function upload(Product $product, UploadedFile $file, bool $isPrimary = false): ProductImage
    {
        // 1. Имя файла и путь внутри S3
        $fileName = Str::uuid() . '.' . ($file->getClientOriginalExtension() ?: 'webp');
        $path = "products/{$product->id}/{$fileName}";

        // 2. Отправка файла в MinIO
        Storage::disk($this->disk)->putFileAs("products/{$product->id}", $file, $fileName, 'public');

        // 3. Если картинок еще нет, первая автоматически становится главной
        $isPrimary = $isPrimary || !$product->images()->exists();

        if ($isPrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        // 4. Порядковый номер (следующий по счету)
        $position = (int) $product->images()->max('position') + 1;

        // 5. Запись в БД
        return $product->images()->create([
            'path'       => $path,
            'url'        => Storage::disk($this->disk)->url($path),
            'is_primary' => $isPrimary,
            'position'   => $position,
        ]);
    }

    /**
     * Удалить изображение из MinIO и БД
     */
    public function delete(ProductImage $image): void
    {
        // 1. Удаляем физический файл из MinIO
        Storage::disk($this->disk)->delete($image->path);

        $productId = $image->product_id;
        $wasPrimary = $image->is_primary;

        // 2. Удаляем запись из БД
        $image->delete();

        // 3. Если удалили главную, назначаем первой оставшуюся
        if ($wasPrimary) {
            ProductImage::where('product_id', $productId)
                ->orderBy('position')
                ->first()
                ?->update(['is_primary' => true]);
        }
    }
}