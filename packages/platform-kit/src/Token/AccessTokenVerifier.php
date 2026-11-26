<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use UnexpectedValueException;

final class AccessTokenVerifier
{
    private const ALLOWED_ALGORITHMS = ['RS256'];

    public function __construct(
        private readonly RsaKeySet $keys,
        private readonly string $issuer,
        private readonly string $audience,
    ) {
        if ($this->issuer === '' || $this->audience === '') {
            throw new TokenInfrastructureException('JWT issuer and audience must be configured.');
        }
    }

    public static function fromJwks(string $jwks, string $issuer, string $audience): self
    {
        return new self(RsaKeySet::fromJwks($jwks), $issuer, $audience);
    }

    public function verify(string $token): AccessClaims
    {
        if ($token === '') {
            throw InvalidTokenException::missing();
        }

        $header = $this->header($token);
        $algorithm = (string) ($header->alg ?? '');
        if (! in_array($algorithm, self::ALLOWED_ALGORITHMS, true)) {
            throw InvalidTokenException::signature();
        }

        $kid = $header->kid ?? null;
        if (! is_string($kid) || $kid === '') {
            throw InvalidTokenException::signature();
        }

        JWT::$leeway = 30;

        try {
            $decoded = JWT::decode($token, $this->keys->key($kid));
        } catch (ExpiredException) {
            throw InvalidTokenException::expired();
        } catch (SignatureInvalidException) {
            throw InvalidTokenException::signature();
        } catch (InvalidTokenException $exception) {
            throw $exception;
        } catch (UnexpectedValueException) {
            throw InvalidTokenException::malformed();
        }

        $this->assertRegisteredClaims($decoded);

        return AccessClaims::fromPayload($decoded);
    }

    private function assertRegisteredClaims(stdClass $claims): void
    {
        $audience = $claims->aud ?? null;
        $accepted = is_array($audience) ? in_array($this->audience, $audience, true) : $audience === $this->audience;
        if (! $accepted) {
            throw InvalidTokenException::audience();
        }

        if (($claims->iss ?? null) !== $this->issuer) {
            throw InvalidTokenException::issuer();
        }

        if (! isset($claims->jti) || ! is_string($claims->jti) || $claims->jti === '') {
            throw InvalidTokenException::malformed();
        }

        if (! isset($claims->nbf) || ! is_numeric($claims->nbf)) {
            throw InvalidTokenException::malformed();
        }
    }

    private function header(string $token): stdClass
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw InvalidTokenException::malformed();
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $header = is_string($json) ? json_decode($json) : null;
        if (! $header instanceof stdClass) {
            throw InvalidTokenException::malformed();
        }

        return $header;
    }
}
