<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Firebase\JWT\JWT;

final class AccessTokenSigner
{
    public function __construct(
        private readonly string $privateKeyPem,
        private readonly string $kid,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttl = 900,
    ) {}

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $overrides
     */
    public function issue(int $userId, array $roles, ?int $storeId = null, array $overrides = []): string
    {
        $now = time();
        $claims = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $userId,
            'jti' => bin2hex(random_bytes(8)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
            'roles' => $roles,
            'type' => TokenType::Access->value,
        ];
        if ($storeId !== null) {
            $claims['store_id'] = $storeId;
        }

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($claims[$key]);
            } else {
                $claims[$key] = $value;
            }
        }

        return JWT::encode($claims, $this->privateKeyPem, 'RS256', $this->kid);
    }
}
