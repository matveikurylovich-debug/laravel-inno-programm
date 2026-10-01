<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Complete login including the two-factor challenge.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    protected function tokensFor(User $user, string $password = 'password'): array
    {
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk();

        $code = Cache::get("2fa_code_{$user->id}");
        $this->assertNotEmpty($code);

        $response = $this->postJson('/api/two-factor/verify', [
            'user_id' => $user->id,
            'code' => $code,
        ])->assertOk();

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
        ];
    }

    protected function tokenFor(User $user, string $password = 'password'): string
    {
        return $this->tokensFor($user, $password)['access_token'];
    }
}
