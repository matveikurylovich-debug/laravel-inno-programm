<?php

declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Throwable;

final class JwtAccessTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly string $jwtAccessSecret,
        private readonly string $jwtAlgo = 'HS256',
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/recommendations');
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get('Authorization', '');
        if (!is_string($header) || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new CustomUserMessageAuthenticationException('Заголовок Authorization: Bearer отсутствует.');
        }

        try {
            $decoded = JWT::decode($matches[1], new Key($this->jwtAccessSecret, $this->jwtAlgo));
        } catch (ExpiredException) {
            throw new CustomUserMessageAuthenticationException('Срок действия access-токена истек.');
        } catch (SignatureInvalidException) {
            throw new CustomUserMessageAuthenticationException('Неверная подпись токена.');
        } catch (Throwable $exception) {
            throw new CustomUserMessageAuthenticationException('Некорректный токен.');
        }

        if (($decoded->type ?? null) !== 'access') {
            throw new CustomUserMessageAuthenticationException('Некорректный тип токена.');
        }

        $userId = (int) ($decoded->sub ?? 0);
        if ($userId <= 0) {
            throw new CustomUserMessageAuthenticationException('В токене нет идентификатора пользователя.');
        }

        $email = is_string($decoded->email ?? null) ? $decoded->email : '';
        $roles = $this->rolesOf($decoded);

        return new SelfValidatingPassport(new UserBadge((string) $userId, static fn (): JwtUser => new JwtUser($userId, $email, $roles)));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey() !== '' && $exception->getMessage() !== $exception->getMessageKey()
                ? $exception->getMessage()
                : 'Требуется авторизация.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return list<string>
     */
    private function rolesOf(object $decoded): array
    {
        $roles = [];
        $raw = $decoded->roles ?? [];
        if (is_array($raw)) {
            foreach ($raw as $role) {
                if (is_string($role) && $role !== '') {
                    $roles[] = str_starts_with($role, 'ROLE_') ? $role : 'ROLE_'.strtoupper($role);
                }
            }
        }

        $primary = $decoded->role ?? null;
        if (is_string($primary) && $primary !== '') {
            $roles[] = $primary === 'admin' ? 'ROLE_ADMIN' : 'ROLE_'.strtoupper($primary);
        }

        return array_values(array_unique($roles));
    }
}
