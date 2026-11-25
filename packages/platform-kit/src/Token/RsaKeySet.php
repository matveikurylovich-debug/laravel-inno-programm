<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Firebase\JWT\Key;

final class RsaKeySet
{
    /**
     * @param  array<string, Key>  $keys
     */
    private function __construct(private readonly array $keys) {}

    public static function fromJwks(string $json): self
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['keys']) || ! is_array($data['keys'])) {
            throw new TokenInfrastructureException('JWKS document is invalid.');
        }

        $keys = [];
        foreach ($data['keys'] as $jwk) {
            if (! is_array($jwk) || ($jwk['kty'] ?? null) !== 'RSA' || ($jwk['use'] ?? 'sig') !== 'sig') {
                continue;
            }
            $alg = (string) ($jwk['alg'] ?? '');
            $kid = (string) ($jwk['kid'] ?? '');
            if ($alg !== 'RS256' || $kid === '' || ! isset($jwk['n'], $jwk['e'])) {
                continue;
            }
            $keys[$kid] = new Key(self::pem((string) $jwk['n'], (string) $jwk['e']), 'RS256');
        }

        if ($keys === []) {
            throw new TokenInfrastructureException('JWKS document has no RS256 keys.');
        }

        return new self($keys);
    }

    public function key(string $kid): Key
    {
        if (! isset($this->keys[$kid])) {
            throw InvalidTokenException::signature();
        }

        return $this->keys[$kid];
    }

    /**
     * @param  array{n: string, e: string, kid: string}  $jwk
     */
    public static function jwk(string $publicPem, string $kid): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicPem));
        if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new TokenInfrastructureException('JWT public key is not RSA.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => self::base64Url($details['rsa']['n']),
            'e' => self::base64Url($details['rsa']['e']),
        ];
    }

    private static function pem(string $modulus, string $exponent): string
    {
        $modulus = self::base64UrlDecode($modulus);
        $exponent = self::base64UrlDecode($exponent);
        $inner = self::sequence(self::integer($modulus).self::integer($exponent));
        $bitString = "\x03".self::length(strlen($inner) + 1)."\x00".$inner;
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $key = self::sequence($algorithm.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($key), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function integer(string $value): string
    {
        if ($value === '' || (ord($value[0]) & 0x80) === 0x80) {
            $value = "\x00".$value;
        }

        return "\x02".self::length(strlen($value)).$value;
    }

    private static function sequence(string $value): string
    {
        return "\x30".self::length(strlen($value)).$value;
    }

    private static function length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;

        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);
    }
}
