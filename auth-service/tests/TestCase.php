<?php

declare(strict_types=1);

namespace Tests;

use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Junges\Kafka\Facades\Kafka;
use Tests\Support\ArrayRedisConnection;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! defined('RD_KAFKA_PARTITION_UA')) {
            define('RD_KAFKA_PARTITION_UA', -1);
        }

        parent::setUp();

        Kafka::fake();
        $this->isolateRedis();
    }

    private function isolateRedis(): void
    {
        if (extension_loaded('redis')) {
            $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);
                Redis::connection()->flushdb();

                return;
            }
        }

        $fake = new ArrayRedisConnection;
        Redis::swap($fake);
        $this->app->instance(RedisFactory::class, $fake);
    }

    /**
     * @return array{challenge_id: string, code: string}
     */
    protected function twoFactorChallenge(User $user, string $password = 'password'): array
    {
        $code = null;
        Event::listen(TwoFactorCodeRequested::class, function (TwoFactorCodeRequested $event) use (&$code): void {
            $code = $event->code;
        });

        $login = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk();

        $challengeId = $login->json('challenge_id');
        $this->assertIsString($challengeId);
        $this->assertIsString($code);

        return [
            'challenge_id' => $challengeId,
            'code' => $code,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    protected function tokensFor(User $user, string $password = 'password'): array
    {
        $challenge = $this->twoFactorChallenge($user, $password);

        $response = $this->postJson('/api/v1/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['code'],
        ])->assertOk();

        $accessToken = $response->json('access_token');
        $refreshToken = $response->json('refresh_token');
        $this->assertIsString($accessToken);
        $this->assertIsString($refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    protected function tokenFor(User $user, string $password = 'password'): string
    {
        return $this->tokensFor($user, $password)['access_token'];
    }
}
