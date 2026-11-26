<?php

declare(strict_types=1);

namespace App\Contracts;

interface LlmClientInterface
{
    /**
     * Преобразует текст в вектор эмбеддинга (массив из 768 чисел float).
     *
     * @return list<float>
     */
    public function createEmbedding(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array;

    /**
     * Извлекает фильтры и интент пользователя из текста сообщения.
     * Возвращает структурированный массив: ['category' => ?, 'price_max' => ?, 'attributes' => []].
     *
     * @param list<string> $categories известные категории магазина; непустой список ограничивает category enum-ом
     *
     * @return array<string, mixed>
     */
    public function extractFilters(string $userMessage, array $categories = []): array;

    /**
     * Генерирует консультационный ответ пользователю на основе найденных товаров (Grounded Generation).
     *
     * @param array<int, array<string, mixed>> $products Список товаров из базы данных
     */
    public function generateRecommendation(string $userMessage, array $products): string;
}
