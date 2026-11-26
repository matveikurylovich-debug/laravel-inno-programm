<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductImageService
{
    protected string $disk = 'minio';

    public function upload(Product $product, UploadedFile $file, bool $isPrimary = false): ProductImage
    {
        $directory = "products/{$product->id}";
        $path = Storage::disk($this->disk)->putFile($directory, $file, 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Не удалось сохранить изображение товара в хранилище.');
        }

        try {
            return DB::transaction(function () use ($product, $path, $isPrimary): ProductImage {
                $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $primary = $isPrimary || ! $locked->images()->exists();

                if ($primary) {
                    $locked->images()->update(['is_primary' => false]);
                }

                $position = (int) $locked->images()->max('position') + 1;

                return $locked->images()->create([
                    'path' => $path,
                    'url' => Storage::disk($this->disk)->url($path),
                    'is_primary' => $primary,
                    'position' => $position,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($this->disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(ProductImage $image): void
    {
        $path = $image->path;
        $productId = $image->product_id;
        $wasPrimary = $image->is_primary;

        DB::transaction(function () use ($image, $productId, $wasPrimary): void {
            $image->delete();

            if ($wasPrimary) {
                ProductImage::query()
                    ->where('product_id', $productId)
                    ->orderBy('position')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });

        DB::afterCommit(function () use ($path): void {
            Storage::disk($this->disk)->delete($path);
        });
    }
}
