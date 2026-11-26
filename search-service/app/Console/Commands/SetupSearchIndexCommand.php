<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ProductIndex;
use Illuminate\Console\Command;
use Override;

class SetupSearchIndexCommand extends Command
{
    #[Override]
    protected $signature = 'search:setup-index {--force : Пересоздать индекс, если он уже есть}';

    #[Override]
    protected $description = 'Создаёт индекс products в Elasticsearch';

    public function handle(ProductIndex $index): int
    {
        $index->setup((bool) $this->option('force'));
        $this->info("Индекс {$index->indexName()} готов");

        return self::SUCCESS;
    }
}
