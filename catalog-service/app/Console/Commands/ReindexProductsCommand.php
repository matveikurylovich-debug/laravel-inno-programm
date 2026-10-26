<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Console\Command;

class ReindexProductsCommand extends Command
{
    protected $signature = 'catalog:reindex-products';

    protected $description = 'Публикует все товары в Kafka (product.updated): Search индекс и product_snapshots в Order Service';

    public function handle(ProductObserver $observer): int
    {
        $count = 0;

        Product::query()->with(['category', 'stock'])->orderBy('id')->each(function (Product $product) use ($observer, &$count): void {
            $observer->updated($product);
            $count++;
        });

        $this->info("Опубликовано товаров: {$count}");

        return self::SUCCESS;
    }
}
