<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group pgsql
 */
class PostgresPersistenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            $this->markTestSkipped('Группа pgsql запускается в CI с DB_CONNECTION=pgsql и остальными DB_* из окружения.');
        }

        parent::setUp();
        mockRedis();
    }
}
