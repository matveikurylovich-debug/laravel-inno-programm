<?php

namespace App\Http\Controllers\Api;

use Ecommerce\PlatformKit\Token\RsaKeySet;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class JwksController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $keys = [];
        $current = $this->publicKey((string) config('jwt.public_key_path'));
        $keys[] = RsaKeySet::jwk($current, (string) config('jwt.kid'));

        $previousPath = (string) config('jwt.previous_public_key_path');
        $previousKid = (string) config('jwt.previous_kid');
        if ($previousPath !== '' && $previousKid !== '' && is_file($previousPath)) {
            $keys[] = RsaKeySet::jwk($this->publicKey($previousPath), $previousKid);
        }

        return response()->json(['keys' => $keys]);
    }

    private function publicKey(string $path): string
    {
        if ($path === '' || ! is_file($path)) {
            throw new TokenInfrastructureException('JWT public key is missing.');
        }

        return (string) file_get_contents($path);
    }
}
