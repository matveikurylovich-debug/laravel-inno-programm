<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

class ProductsUnavailable extends RuntimeException implements ProvidesProblem
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(public readonly array $ids)
    {
        parent::__construct('Один или несколько товаров недоступны в этом магазине');
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemDetail(): string
    {
        return $this->getMessage();
    }

    public function problemErrors(): array
    {
        return ['product_ids' => $this->ids];
    }
}
