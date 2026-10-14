<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PDOException;

class PostgresPersistenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! $this->enablePostgres()) {
            $this->markTestSkipped('PostgreSQL недоступен (order-postgres:5432 или 127.0.0.1:5435)');
        }

        parent::setUp();
        mockRedis();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreSqlite();
    }

    private function enablePostgres(): bool
    {
        $env = $this->envFile();
        $password = $env['DB_PASSWORD'] ?? '';
        $username = $env['DB_USERNAME'] ?? 'order';

        if ($password === '') {
            return false;
        }

        $hosts = [
            [$env['DB_HOST'] ?? 'order-postgres', $env['DB_PORT'] ?? '5432'],
            ['127.0.0.1', $env['FORWARD_DB_PORT'] ?? '5435'],
        ];

        $connected = null;

        foreach ($hosts as [$host, $port]) {
            try {
                $admin = new PDO("pgsql:host={$host};port={$port};dbname=order_db", $username, $password);
                $admin->exec('CREATE DATABASE order_testing');
                $connected = [$host, $port];
                break;
            } catch (PDOException $exception) {
                if (str_contains($exception->getMessage(), 'already exists')) {
                    $connected = [$host, $port];
                    break;
                }
            }
        }

        if ($connected === null) {
            return false;
        }

        [$host, $port] = $connected;
        $this->setEnv([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => 'order_testing',
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'DB_URL' => '',
        ]);

        return true;
    }

    private function restoreSqlite(): void
    {
        $this->setEnv([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @return array<string, string>
     */
    private function envFile(): array
    {
        $path = dirname(__DIR__).'/.env';
        if (! is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (! str_contains($line, '=') || str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }
}
