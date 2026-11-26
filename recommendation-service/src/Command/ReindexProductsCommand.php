<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductProjection;
use App\Service\ProductIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'recommendation:reindex-products', description: 'Rebuild embeddings from the local catalog projection')]
final class ReindexProductsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductIndexer $productIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var iterable<ProductProjection> $rows */
        $rows = $this->entityManager->createQuery('SELECT p FROM '.ProductProjection::class.' p')->toIterable();
        $count = 0;
        foreach ($rows as $product) {
            $this->productIndexer->reindexProjection($product);
            $this->entityManager->detach($product);
            ++$count;
        }

        $output->writeln(\sprintf('Reindexed %d products.', $count));

        return Command::SUCCESS;
    }
}
