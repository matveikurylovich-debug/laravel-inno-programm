<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\LlmClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:embedding',
    description: 'Тестирует получение вектора эмбеддинга через Gemini API'
)]
final class TestEmbeddingCommand extends Command
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $text = 'Тестовое описание товара: Зимняя куртка с мембраной';
        $output->writeln(sprintf('<info>Отправка запроса на векторизацию:</info> "%s"', $text));

        $vector = $this->llmClient->createEmbedding($text);

        $output->writeln(sprintf('<info>Успешно! Получен вектор размерностью:</info> %d элементов', count($vector)));
        $output->writeln(sprintf('Первые 5 значений: [%s, ...]', implode(', ', array_slice($vector, 0, 5))));

        return Command::SUCCESS;
    }
}