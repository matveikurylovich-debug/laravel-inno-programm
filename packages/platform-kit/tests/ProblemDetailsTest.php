<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Tests;

use Ecommerce\PlatformKit\Http\ProblemDetails;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsTest extends TestCase
{
    public function test_hides_model_class_on_not_found(): void
    {
        $detail = ProblemDetails::detail(404, 'No query results for model [App\\Models\\Order] abc');

        $this->assertSame('Ресурс не найден.', $detail);
        $payload = ProblemDetails::make(404, $detail);

        $this->assertSame('about:blank', $payload['type']);
        $this->assertSame('Not Found', $payload['title']);
        $this->assertSame(404, $payload['status']);
        $this->assertSame('not_found', $payload['code']);
        $this->assertSame('Ресурс не найден.', $payload['message']);
        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertStringNotContainsString('App\\Models', $payload['detail']);
    }

    public function test_keeps_a_user_facing_reason_and_field_errors(): void
    {
        $detail = ProblemDetails::firstError(['email' => ['Email уже занят.']]);
        $payload = ProblemDetails::make(422, (string) $detail, ['email' => ['Email уже занят.']], 'urn:problem:validation', 'email_taken');

        $this->assertSame('Email уже занят.', $payload['detail']);
        $this->assertSame('Email уже занят.', $payload['message']);
        $this->assertSame('email_taken', $payload['code']);
        $this->assertSame('Unprocessable Entity', $payload['title']);
        $this->assertSame(['email' => ['Email уже занят.']], $payload['errors']);
    }
}
