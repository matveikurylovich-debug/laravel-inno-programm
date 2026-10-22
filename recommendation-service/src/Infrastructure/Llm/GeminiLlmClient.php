<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Contract\LlmClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class GeminiLlmClient implements LlmClientInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $geminiApiKey,
        private readonly string $embeddingModel = 'gemini-embedding-001',
        private readonly string $chatModel = 'gemini-3.5-flash',
    ) {
    }

    /**
     * @return array<float>
     */
    public function createEmbedding(string $text): array
    {
        $url = sprintf('%s/%s:embedContent', self::BASE_URL, $this->embeddingModel);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'x-goog-api-key' => $this->geminiApiKey,
                ],
                'json' => [
                    'model' => 'models/'.$this->embeddingModel,
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                    'outputDimensionality' => 768,
                ],
                'timeout' => 5.0,
            ]);

            $data = $response->toArray();

            return $data['embedding']['values'] ?? [];
        } catch (Throwable $e) {
            $this->logger->error('Ошибка вычисления эмбеддинга через Gemini API', [
                'error' => $e->getMessage(),
                'text' => mb_substr($text, 0, 100),
            ]);

            throw new RuntimeException('Не удалось получить вектор эмбеддинга: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFilters(string $userMessage): array
    {
        $url = sprintf('%s/%s:generateContent', self::BASE_URL, $this->chatModel);

        $prompt = <<<PROMPT
Проанализируй запрос покупателя интернет-магазина и извлеки параметры поиска в формате строгого JSON.
Если параметра нет в запросе — верни null.

Схема JSON:
{
  "category": "строка или null",
  "price_max": число или null,
  "attributes": {
    "color": "строка или null",
    "material": "строка или null"
  }
}

Запрос покупателя: "{$userMessage}"
Ответ отдай ТОЛЬКО чистым валидным JSON без Markdown-разметки (без ```json).
PROMPT;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'x-goog-api-key' => $this->geminiApiKey,
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.1,
                    ],
                ],
                'timeout' => 6.0,
            ]);

            $data = $response->toArray();
            $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            $this->logger->warning('Сбой при извлечении фильтров через LLM, используем пустые фильтры', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public function generateRecommendation(string $userMessage, array $products): string
    {
        if ($products === []) {
            return 'К сожалению, по вашему запросу ничего не нашлось. Попробуйте уточнить параметры или категорию товара.';
        }

        $url = sprintf('%s/%s:generateContent', self::BASE_URL, $this->chatModel);
        $promptProducts = array_map(static function (array $product): array {
            unset($product['product_url']);

            return $product;
        }, $products);
        $productsJson = json_encode($promptProducts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $systemInstruction = <<<'TEXT'
Ты — профессиональный AI-консультант интернет-магазина.
Твоя задача — порекомендовать подходящие товары из переданного списка под запрос покупателя.

Правила:
1. Отвечай вежливо, дружелюбно и кратко, по делу.
2. Ссылайся только на товары из переданного списка.
3. Не выдумывай несуществующие товары, цены и характеристики.
4. Объясни, почему предложенный товар подходит под запрос.
5. Пиши обычными предложениями. Без Markdown, без звёздочек, без ссылок и без адресов страниц.
6. Называй товар по имени. Карточку с ценой покупатель видит отдельно.
TEXT;

        $userPrompt = <<<PROMPT
Запрос покупателя: {$userMessage}

Список доступных подходящих товаров:
{$productsJson}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'x-goog-api-key' => $this->geminiApiKey,
                ],
                'json' => [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemInstruction],
                        ],
                    ],
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                    ],
                ],
                'timeout' => 8.0,
            ]);

            $data = $response->toArray();

            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? 'Вот товары, подходящие под ваш запрос:';
        } catch (Throwable $e) {
            $this->logger->error('Ошибка генерации ответа консультанта через Gemini, возврат fallback', [
                'error' => $e->getMessage(),
            ]);

            return 'Вот товары, которые точно соответствуют вашему запросу:';
        }
    }
}
