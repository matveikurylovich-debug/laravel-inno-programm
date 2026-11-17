<?php

declare(strict_types=1);
namespace App\Command;

use App\Contract\LlmClientInterface;
use App\Entity\ProductProjection;
use App\Service\ProductTextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:products:reindex', description: 'Rebuild embeddings from the local catalog projection')]
final class ReindexProductsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly LlmClientInterface $llm, private readonly ProductTextBuilder $textBuilder) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->entityManager->createQuery('SELECT p FROM '.ProductProjection::class.' p')->toIterable();
        $count = 0;
        foreach ($rows as $product) {
            $text = $this->textBuilder->build($product->getName(), $product->getDescription(), $product->getCategoryName(), $product->getAttributes());
            $embedding = $this->llm->createEmbedding($text);
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE product_projections SET embedding = CAST(:embedding AS vector), updated_at = NOW() WHERE product_id = :id',
                ['embedding' => '['.implode(',', $embedding).']', 'id' => $product->getProductId()],
            );
            $this->entityManager->detach($product);
            ++$count;
        }
        $output->writeln(sprintf('Reindexed %d products.', $count));
        return Command::SUCCESS;
    }
}
