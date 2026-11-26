<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use stdClass;

final readonly class AccessClaims
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $userId,
        public array $roles,
        public TokenType $type,
        public int $exp,
        public int $iat,
        public string $jti,
        public int $nbf,
        public ?int $storeId = null,
        public ?string $email = null,
    ) {}

    public static function fromPayload(stdClass $payload): self
    {
        $type = TokenType::tryFrom((string) ($payload->type ?? ''));
        if ($type !== TokenType::Access) {
            throw InvalidTokenException::type();
        }

        if (! isset($payload->sub) || ! is_numeric($payload->sub)) {
            throw InvalidTokenException::malformed();
        }

        if (! isset($payload->jti) || ! is_string($payload->jti) || $payload->jti === '') {
            throw InvalidTokenException::malformed();
        }
        if (! isset($payload->nbf) || ! is_numeric($payload->nbf)) {
            throw InvalidTokenException::malformed();
        }

        $roles = [];
        if (isset($payload->roles)) {
            $raw = is_array($payload->roles) ? $payload->roles : (array) $payload->roles;
            foreach ($raw as $role) {
                $roles[] = (string) $role;
            }
        }

        return new self(
            userId: (int) $payload->sub,
            roles: $roles,
            type: $type,
            exp: (int) ($payload->exp ?? 0),
            iat: (int) ($payload->iat ?? 0),
            jti: $payload->jti,
            nbf: (int) $payload->nbf,
            storeId: isset($payload->store_id) && is_numeric($payload->store_id) ? (int) $payload->store_id : null,
            email: isset($payload->email) && is_string($payload->email) && $payload->email !== '' ? $payload->email : null,
        );
    }
}
