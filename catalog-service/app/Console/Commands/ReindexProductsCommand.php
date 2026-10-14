<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Console\Command;

class ReindexProductsCommand extends Command
{
    protected $signature = 'catalog:reindex-products';

    protected $description = 'Повторно публикует товары в Kafka, чтобы Search Service построил индекс';

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
