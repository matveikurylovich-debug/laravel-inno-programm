<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\LlmClientInterface;
use App\Dto\SearchFilters;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageRole;
use App\Repository\ProductProjectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final readonly class RecommendationEngine
{
    public function __construct(
        private LlmClientInterface $llmClient,
        private ProductProjectionRepository $productRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private CategoryCatalog $categories,
        private ClockInterface $clock = new NativeClock(),
        #[Autowire(env: 'float:SIMILARITY_THRESHOLD')]
        private float $similarityThreshold = 0.57,
        #[Autowire(param: 'recommendation.max_products_limit')]
        private int $maxProductsLimit = 5,
    ) {
    }

    /**
     * @return array{
     *     reply: string,
     *     products: list<array<string, mixed>>,
     *     filters: SearchFilters
     * }
     */
    public function processUserMessage(
        Conversation $conversation,
        string $userMessage,
    ): array {
        $effectiveStoreId = $conversation->getStoreId();
        $storeCategories = $this->categories->names($effectiveStoreId);
        $extractedFilters = $this->filtersFromLlm($this->llmClient->extractFilters($userMessage, $storeCategories));
        $queryEmbedding = $this->llmClient->createEmbedding($userMessage, 'RETRIEVAL_QUERY');
        $matchedProducts = $this->productRepository->searchSimilar(
            embedding: $queryEmbedding,
            storeId: $effectiveStoreId,
            filters: $extractedFilters,
            limit: $this->maxProductsLimit,
            threshold: $this->similarityThreshold,
        );

        if ([] === $matchedProducts && $extractedFilters->hasSoftConstraints()) {
            $this->logger->info('Повторный поиск без мягких ограничений category/attributes', [
                'store_id' => $effectiveStoreId,
                'filters' => $extractedFilters->toArray(),
            ]);
            $matchedProducts = $this->productRepository->searchSimilar(
                embedding: $queryEmbedding,
                storeId: $effectiveStoreId,
                filters: $extractedFilters->withoutSoftConstraints(),
                limit: $this->maxProductsLimit,
                threshold: $this->similarityThreshold,
            );
        }

        $this->logger->info('Поиск товаров для рекомендаций завершен', [
            'store_id' => $effectiveStoreId,
            'filters' => $extractedFilters->toArray(),
            'found_count' => \count($matchedProducts),
        ]);

        $assistantReply = AssistantReply::sanitize(
            $this->llmClient->generateRecommendation($userMessage, $matchedProducts),
        );
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
            'filters' => $extractedFilters,
        ];
    }

    /**
     * @param list<array<string, mixed>> $matchedProducts
     */
    private function persistMessages(
        Conversation $conversation,
        string $userText,
        string $assistantText,
        SearchFilters $extractedFilters,
        array $matchedProducts,
    ): void {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use (
            $conversation,
            $userText,
            $assistantText,
            $extractedFilters,
            $matchedProducts,
        ): void {
            $now = $this->clock->now();
            if (!$em->contains($conversation)) {
                $em->persist($conversation);
            }

            $userMessage = new Message($conversation, MessageRole::User, $userText, $now);
            $userMessage->setExtractedFilters($extractedFilters->toArray());

            $assistantMessage = new Message($conversation, MessageRole::Assistant, $assistantText, $now);
            $assistantMessage->setSuggestedProducts($matchedProducts);

            $conversation->addMessage($userMessage, $now);
            $conversation->addMessage($assistantMessage, $now);
            $conversation->touchActivity($now);

            $em->persist($userMessage);
            $em->persist($assistantMessage);
        });
    }

    private function filtersFromLlm(mixed $payload): SearchFilters
    {
        try {
            $filters = $this->denormalizer->denormalize(
                $this->cleanFilterPayload($payload),
                SearchFilters::class,
                'json',
            );
        } catch (Throwable $exception) {
            $this->logger->warning('Не удалось разобрать фильтры LLM', [
                'error' => $exception->getMessage(),
            ]);

            return new SearchFilters();
        }

        return $this->accept($filters);
    }

    /**
     * @return array{category: ?string, price_max: ?float, attributes: array<string, string>}
     */
    private function cleanFilterPayload(mixed $payload): array
    {
        if (!\is_array($payload)) {
            return ['category' => null, 'price_max' => null, 'attributes' => []];
        }

        $category = $payload['category'] ?? null;
        if (!\is_string($category) || '' === trim($category)) {
            $category = null;
        } else {
            $category = trim($category);
        }

        $price = $payload['price_max'] ?? null;
        if (\is_string($price) && is_numeric($price)) {
            $price = (float) $price;
        } elseif (\is_int($price) || \is_float($price)) {
            $price = (float) $price;
        } else {
            $price = null;
        }
        if (null !== $price && !is_finite($price)) {
            $price = null;
        }

        $attributes = [];
        $rawAttributes = $payload['attributes'] ?? [];
        if (\is_array($rawAttributes)) {
            foreach ($rawAttributes as $key => $value) {
                if (!\is_string($key) || !\is_scalar($value)) {
                    continue;
                }
                $text = trim((string) $value);
                if ('' !== $text) {
                    $attributes[$key] = $text;
                }
            }
        }

        return [
            'category' => $category,
            'price_max' => $price,
            'attributes' => $attributes,
        ];
    }

    private function accept(SearchFilters $filters): SearchFilters
    {
        $violations = $this->validator->validate($filters);
        if (0 === \count($violations)) {
            return $filters;
        }

        $category = $filters->category;
        $price = $filters->price_max;
        $attributes = $filters->attributes;
        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath();
            if ('category' === $path) {
                $category = null;
            } elseif ('price_max' === $path) {
                $price = null;
            } elseif ('attributes' === $path || str_starts_with($path, 'attributes[')) {
                $attributes = [];
            }
        }

        $repaired = new SearchFilters($category, $price, $attributes);
        if (\count($this->validator->validate($repaired)) > 0) {
            $this->logger->warning('Фильтры LLM отброшены: валидация не пройдена');

            return new SearchFilters();
        }

        $this->logger->info('Часть фильтров LLM отброшена валидацией');

        return $repaired;
    }
}
