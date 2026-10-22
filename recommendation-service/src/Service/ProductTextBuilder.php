<?php

declare(strict_types=1);

namespace App\Service;

final class ProductTextBuilder
{
    /**
     * Преобразует поля товара в структурированный текст для генерации эмбеддинга.
     *
     * @param array<string, mixed> $attributes
     */
    public function build(
        string $name,
        ?string $description,
        string $category,
        array $attributes = [],
    ): string {
        $parts = [
            'Категория: ' . trim($category),
            'Название: ' . trim($name),
        ];

        if ($description !== null && trim($description) !== '') {
            $parts[] = 'Описание: ' . trim($description);
        }

        if ($attributes !== []) {
            $attrStrings = [];
            foreach ($attributes as $key => $value) {
                if (is_scalar($value) && (string) $value !== '') {
                    $attrStrings[] = sprintf('%s: %s', $key, $value);
                }
            }

            if ($attrStrings !== []) {
                $parts[] = 'Характеристики: ' . implode(', ', $attrStrings);
            }
        }

        return implode("\n", $parts);
    }
}