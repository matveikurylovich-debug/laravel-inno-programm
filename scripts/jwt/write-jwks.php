<?php

declare(strict_types=1);

$directory = $argv[1] ?? '';
if ($directory === '' || ! is_file($directory.'/current.pub.pem')) {
    fwrite(STDERR, "usage: php scripts/jwt/write-jwks.php .secrets/jwt\n");
    exit(1);
}

$keys = [jwk($directory.'/current.pub.pem', getenv('JWT_ACCESS_KID') ?: 'auth-1')];
$previousKid = getenv('JWT_ACCESS_PREVIOUS_KID') ?: '';
if ($previousKid !== '' && is_file($directory.'/previous.pub.pem')) {
    $keys[] = jwk($directory.'/previous.pub.pem', $previousKid);
}

file_put_contents($directory.'/jwks.json', json_encode(['keys' => $keys], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

function jwk(string $path, string $kid): array
{
    $details = openssl_pkey_get_details(openssl_pkey_get_public((string) file_get_contents($path)));
    if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
        fwrite(STDERR, "public key is not RSA: {$path}\n");
        exit(1);
    }

    return [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $kid,
        'n' => b64($details['rsa']['n']),
        'e' => b64($details['rsa']['e']),
    ];
}

function b64(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
