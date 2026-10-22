<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChatRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $message,
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $store_id,
        #[Assert\Uuid]
        public ?string $conversation_id = null,
    ) {
    }
}
