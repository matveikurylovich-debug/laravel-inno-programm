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
        public ?int $storeId = null,
        public ?string $email = null,
        public ?string $role = null,
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

        $roles = [];
        if (isset($payload->roles)) {
            $raw = is_array($payload->roles) ? $payload->roles : (array) $payload->roles;
            foreach ($raw as $role) {
                $roles[] = (string) $role;
            }
        } elseif (isset($payload->role)) {
            $roles[] = (string) $payload->role;
        }

        return new self(
            userId: (int) $payload->sub,
            roles: $roles,
            type: $type,
            exp: (int) ($payload->exp ?? 0),
            iat: (int) ($payload->iat ?? 0),
            storeId: isset($payload->store_id) && is_numeric($payload->store_id) ? (int) $payload->store_id : null,
            email: isset($payload->email) && is_string($payload->email) && $payload->email !== '' ? $payload->email : null,
            role: isset($payload->role) && is_string($payload->role) && $payload->role !== '' ? $payload->role : ($roles[0] ?? null),
        );
    }
}
