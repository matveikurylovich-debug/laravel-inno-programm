<?php

declare(strict_types=1);

namespace App\Support;

use JsonSerializable;

final readonly class TokenPair implements JsonSerializable
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,
        public int $expiresIn,
    ) {}

    /**
     * @param  array{access_token: string, refresh_token: string, token_type: string, expires_in: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            tokenType: $data['token_type'],
            expiresIn: $data['expires_in'],
        );
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
