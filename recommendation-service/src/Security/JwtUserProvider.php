<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<JwtUser>
 */
final class JwtUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('JWT users are loaded from the access token.');
    }

    public function refreshUser(UserInterface $user): JwtUser
    {
        if (!$user instanceof JwtUser) {
            throw new UnsupportedUserException('Unsupported user class '.$user::class);
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return JwtUser::class === $class;
    }
}
