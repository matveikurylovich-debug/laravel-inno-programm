<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\LlmClientInterface;
use App\Repository\ProductProjectionRepository;
use App\Service\ProductTextBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:vector-search',
    description: 'Наполняет базу тестовыми товарами и выполняет семантический поиск'
)]
final class TestVectorSearchCommand extends Command
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly ProductProjectionRepository $repository,
        private readonly ProductTextBuilder $textBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>1. Заполняем базу тестовыми товарами...</info>');

        $products = [
            [
                'product_id' => 101,
                'store_id' => 1,
                'name' => 'Зимние водонепроницаемые кроссовки Salomon Gore-Tex',
                'description' => 'Обувь с глубоким протектором и мембраной для бега по заснеженному грунту и гололёду',
                'category' => 'Обувь',
                'price' => 14500.00,
                'is_published' => true,
                'is_in_stock' => true,
                'attributes' => ['color' => 'черный', 'material' => 'мембрана'],
            ],
            [
                'product_id' => 102,
                'store_id' => 1,
                'name' => 'Летние пляжные шлёпанцы с открытым носком',
                'description' => 'Мягкие резиновые сланцы для бассейна, пляжа и отдыха на солнце',
                'category' => 'Обувь',
                'price' => 1200.00,
                'is_published' => true,
                'is_in_stock' => true,
                'attributes' => ['color' => 'синий', 'material' => 'резина'],
            ],
            [
                'product_id' => 103,
                'store_id' => 1,
                'name' => 'Ударная дрель-шуруповерт Bosch Professional',
                'description' => 'Бесщеточный инструмент для сверления отверстий в бетоне и кирпиче',
                'category' => 'Инструменты',
                'price' => 18900.00,
                'is_published' => true,
                'is_in_stock' => true,
                'attributes' => ['power' => '18V'],
            ],
        ];

        foreach ($products as $item) {
            $text = $this->textBuilder->build(
                $item['name'],
                $item['description'],
                $item['category'],
                $item['attributes']
            );

            $output->write(sprintf('  - Индексация "%s"... ', $item['name']));
            $embedding = $this->llmClient->createEmbedding($text);

            $item['embedding'] = $embedding;
            $this->repository->upsertProjection($item);
            $output->writeln('<info>OK</info>');
        }

        $output->writeln("\n<info>2. Выполняем семантический поиск по запросу:</info>");
        $userQuery = 'хочу побегать по заснеженному парку в мороз';
        $output->writeln(sprintf('   Запрос: "<comment>%s</comment>"', $userQuery));

        // Получаем вектор пользовательского запроса
        $queryEmbedding = $this->llmClient->createEmbedding($userQuery);

        // Ищем в БД через pgvector с порогом 0.5 для теста
        $results = $this->repository->searchSimilar(
            embedding: $queryEmbedding,
            storeId: 1,
            filters: null,
            limit: 3,
            threshold: 0.50
        );

        $output->writeln(sprintf("\nНайдено релевантных товаров: %d\n", count($results)));

        foreach ($results as $result) {
            $output->writeln(sprintf(
                '  [Сходство: <info>%s</info>] ID: %d | %s | %s руб. (Категория: %s)',
                $result['similarity'],
                $result['product_id'],
                $result['name'],
                $result['price'],
                $result['category']
            ));
        }

        return Command::SUCCESS;
    }
}