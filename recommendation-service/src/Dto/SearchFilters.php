<?php

declare(strict_types=1);

namespace App\Dto;

use stdClass;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[Context(
    normalizationContext: [
        AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        AbstractNormalizer::CALLBACKS => [
            'attributes' => [self::class, 'attributesAsObject'],
        ],
    ],
)]
final readonly class SearchFilters
{
    /**
     * @param array<int|string, mixed> $attributes
     */
    public function __construct(
        #[Assert\Length(max: 150)]
        public ?string $category = null,
        #[Assert\PositiveOrZero]
        public ?float $price_max = null,
        #[Assert\Count(max: 20)]
        public array $attributes = [],
    ) {
    }

    #[Ignore]
    public static function attributesAsObject(mixed $attributes): stdClass
    {
        if ($attributes instanceof stdClass) {
            return $attributes;
        }

        if (!\is_array($attributes)) {
            return new stdClass();
        }

        $object = new stdClass();
        foreach (new self(attributes: $attributes)->comparableAttributes() as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }

    #[Ignore]
    public function categoryOrNull(): ?string
    {
        if (null === $this->category) {
            return null;
        }

        $category = trim($this->category);

        return '' === $category ? null : $category;
    }

    #[Ignore]
    public function priceMax(): ?float
    {
        return $this->price_max;
    }

    #[Ignore]
    public function hasSoftConstraints(): bool
    {
        return null !== $this->categoryOrNull() || [] !== $this->comparableAttributes();
    }

    #[Ignore]
    public function withoutSoftConstraints(): self
    {
        return new self(price_max: $this->price_max, attributes: []);
    }

    /**
     * @return array<string, string>
     */
    #[Ignore]
    public function comparableAttributes(): array
    {
        $pairs = [];
        foreach ($this->attributes as $key => $value) {
            if (!\is_string($key) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $key)) {
                continue;
            }
            if (!\is_scalar($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ('' === $text) {
                continue;
            }
            $pairs[$key] = $text;
        }

        return $pairs;
    }

    /**
     * @return array{category: ?string, price_max: ?float, attributes: array<string, string>}
     */
    #[Ignore]
    public function toArray(): array
    {
        return [
            'category' => $this->categoryOrNull(),
            'price_max' => $this->priceMax(),
            'attributes' => $this->comparableAttributes(),
        ];
    }
}
