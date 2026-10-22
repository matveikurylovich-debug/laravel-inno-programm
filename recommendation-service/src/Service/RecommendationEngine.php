<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\LlmClientInterface;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ProductProjectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class RecommendationEngine
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly ProductProjectionRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly float $similarityThreshold = 0.57,
        private readonly int $maxProductsLimit = 5,
    ) {
    }

    /**
     * @return array{
     *     reply: string,
     *     products: array<int, array<string, mixed>>,
     *     extracted_filters: array<string, mixed>
     * }
     */
    public function processUserMessage(
        Conversation $conversation,
        string $userMessage,
        int $storeId,
    ): array {
        // 1. Извлечение структурированных фильтров из текста
        $extractedFilters = $this->llmClient->extractFilters($userMessage);

        // 2. Векторизация пользовательского запроса
        $queryEmbedding = $this->llmClient->createEmbedding($userMessage);

        // 3. Семантический поиск релевантных товаров в pgvector с учетом фильтров
        $matchedProducts = $this->productRepository->searchSimilar(
            embedding: $queryEmbedding,
            storeId: $storeId,
            filters: $extractedFilters,
            limit: $this->maxProductsLimit,
            threshold: $this->similarityThreshold,
        );

        $this->logger->info('Поиск товаров для рекомендаций завершен', [
            'store_id' => $storeId,
            'filters' => $extractedFilters,
            'found_count' => count($matchedProducts),
        ]);

        // 4. Генерация итогового текста рекомендации ассистента
        $assistantReply = $this->llmClient->generateRecommendation($userMessage, $matchedProducts);

        // 5. Сохранение сообщений в историю диалога
        $this->persistMessages(
            conversation: $conversation,
            userText: $userMessage,
            assistantText: $assistantReply,
            extractedFilters: $extractedFilters,
            matchedProducts: $matchedProducts,
        );

        return [
            'reply' => $assistantReply,
            'products' => $matchedProducts,
            'extracted_filters' => $extractedFilters,
        ];
    }

    /**
     * @param array<string, mixed> $extractedFilters
     * @param array<int, array<string, mixed>> $matchedProducts
     */
    private function persistMessages(
        Conversation $conversation,
        string $userText,
        string $assistantText,
        array $extractedFilters,
        array $matchedProducts,
    ): void {
        $userMessage = new Message($conversation, Message::ROLE_USER, $userText);
        $userMessage->setExtractedFilters($extractedFilters);

        $assistantMessage = new Message($conversation, Message::ROLE_ASSISTANT, $assistantText);
        $assistantMessage->setSuggestedProducts($matchedProducts);

        $conversation->addMessage($userMessage);
        $conversation->addMessage($assistantMessage);
        $conversation->touchActivity();

        $this->entityManager->persist($userMessage);
        $this->entityManager->persist($assistantMessage);
        $this->entityManager->flush();
    }
}