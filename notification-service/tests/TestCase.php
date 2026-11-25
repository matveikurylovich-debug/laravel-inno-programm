<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Junges\Kafka\Facades\Kafka;

/**
 * @group mongo
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! defined('RD_KAFKA_PARTITION_UA')) {
            define('RD_KAFKA_PARTITION_UA', -1);
        }

        $socket = @fsockopen('127.0.0.1', 27017, $errno, $errstr, 0.2);
        if ($socket === false) {
            $this->markTestSkipped('Группа mongo: MongoDB на 127.0.0.1:27017 недоступен.');
        }
        fclose($socket);

        parent::setUp();
        Kafka::fake();
    }
}
