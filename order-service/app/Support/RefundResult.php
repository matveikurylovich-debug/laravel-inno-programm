<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RefundState;

final readonly class RefundResult
{
    public function __construct(
        public RefundState $state,
        public string $refundId,
        public ?string $amount = null,
        public ?string $currency = null,
    ) {}
}
