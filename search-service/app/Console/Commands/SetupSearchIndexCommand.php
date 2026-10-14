<?php

namespace App\Console\Commands;

use App\Contracts\ProductIndex;
use Illuminate\Console\Command;

class SetupSearchIndexCommand extends Command
{
    protected $signature = 'search:setup-index {--force : Пересоздать индекс, если он уже есть}';

    protected $description = 'Создаёт индекс products в Elasticsearch';

    public function handle(ProductIndex $index): int
    {
        $index->setup((bool) $this->option('force'));
        $this->info("Индекс {$index->indexName()} готов");

        return self::SUCCESS;
    }
}
