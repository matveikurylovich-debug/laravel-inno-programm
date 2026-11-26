<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Token\AccessClaims;
use Illuminate\Contracts\Auth\Authenticatable;

final class JwtUser implements Authenticatable
{
    public function __construct(public readonly AccessClaims $claims) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->claims->userId;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->claims->roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtolower($role), array_map(strtolower(...), $this->claims->roles), true);
    }
}
