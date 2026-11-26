<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Products\ProductEventPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Публикует все товары в Kafka (product.updated): Search индекс и product_snapshots в Order Service')]
#[Signature('catalog:reindex-products')]
class ReindexProductsCommand extends Command
{
    public function handle(ProductEventPublisher $publisher): int
    {
        $count = 0;

        Product::query()->with(['category', 'stock'])->orderBy('id')->each(function (Product $product) use ($publisher, &$count): void {
            $publisher->publishUpdated($product);
            $count++;
        });

        $this->info("Опубликовано товаров: {$count}");

        return self::SUCCESS;
    }
}
