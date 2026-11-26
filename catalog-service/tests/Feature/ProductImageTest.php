<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\Products\ProductImageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createImageTestProduct(): Product
{
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-img', 'is_active' => true]);
    $category = Category::query()->create([
        'store_id' => $store->id,
        'name' => 'Phones',
        'slug' => 'phones',
    ]);

    return Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'SKU-IMG',
        'name' => 'Phone',
        'slug' => 'phone-img',
        'price' => 10,
        'is_active' => true,
        'attributes' => ['brand' => 'Acme'],
    ]);
}

it('does not create a database row when MinIO write fails', function (): void {
    $product = createImageTestProduct();
    Storage::fake('minio');
    $disk = Storage::disk('minio');
    $disk->buildTemporaryUrlsUsing(static fn (): string => 'https://minio.test/file');

    Storage::shouldReceive('disk')->with('minio')->andReturnUsing(static fn (): object => new readonly class($disk)
    {
        public function __construct(private Filesystem $disk) {}

        public function putFile(): string
        {
            throw new RuntimeException('minio unavailable');
        }

        public function delete(): bool
        {
            return true;
        }

        public function url(string $path): string
        {
            return $this->disk->url($path);
        }
    });

    expect(static fn (): ProductImage => resolve(ProductImageService::class)->upload(
        $product,
        UploadedFile::fake()->create('phone.jpg', 10, 'image/jpeg'),
        true,
    ))->toThrow(RuntimeException::class, 'minio unavailable');

    expect(ProductImage::query()->count())->toBe(0);
});

it('replaces the first primary image when uploading a second primary image', function (): void {
    $product = createImageTestProduct();
    Storage::fake('minio');
    $service = resolve(ProductImageService::class);
    $file = UploadedFile::fake()->create('phone.jpg', 10, 'image/jpeg');

    $service->upload($product, $file, true);
    $service->upload($product, $file, true);

    expect(ProductImage::query()->count())->toBe(2)
        ->and(ProductImage::query()->where('is_primary', true)->count())->toBe(1);
});
