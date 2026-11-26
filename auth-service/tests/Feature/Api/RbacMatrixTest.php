<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

dataset('rbac_matrix', [
    'GET /api/v1/me' => ['GET', '/api/v1/me', [], [
        'admin' => 200,
        'analyst' => 200,
        'customer' => 200,
        'guest' => 401,
    ]],
    'GET /api/v1/gateway/verify' => ['GET', '/api/v1/gateway/verify', [], [
        'admin' => 204,
        'analyst' => 204,
        'customer' => 204,
        'guest' => 401,
    ]],
    'POST /api/v1/logout-all' => ['POST', '/api/v1/logout-all', [], [
        'admin' => 200,
        'analyst' => 200,
        'customer' => 200,
        'guest' => 401,
    ]],
    'GET /api/v1/admin/users' => ['GET', '/api/v1/admin/users', [], [
        'admin' => 200,
        'analyst' => 200,
        'customer' => 403,
        'guest' => 401,
    ]],
    'GET /api/v1/admin/roles' => ['GET', '/api/v1/admin/roles', [], [
        'admin' => 200,
        'analyst' => 200,
        'customer' => 403,
        'guest' => 401,
    ]],
    'PATCH /api/v1/admin/users/{target}/role' => ['PATCH', '/api/v1/admin/users/{target}/role', ['role' => 'analyst'], [
        'admin' => 200,
        'analyst' => 403,
        'customer' => 403,
        'guest' => 401,
    ]],
    'GET /api/v1/catalog-admin/access' => ['GET', '/api/v1/catalog-admin/access', [], [
        'admin' => 204,
        'analyst' => 403,
        'customer' => 403,
        'guest' => 401,
    ]],
]);

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, int>  $expectedStatuses
 */
it('enforces RBAC access matrix across roles', function (string $method, string $uriTemplate, array $payload, array $expectedStatuses): void {
    $target = User::factory()->create();
    $target->assignRole(RoleName::Customer);

    $uri = str_replace('{target}', (string) $target->id, $uriTemplate);

    foreach ($expectedStatuses as $role => $expectedStatus) {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        if ($role === 'guest') {
            $this->json($method, $uri, $payload)->assertStatus($expectedStatus);

            continue;
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);
        $token = $this->tokenFor($actor);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->json($method, $uri, $payload)
            ->assertStatus($expectedStatus);
    }
})->with('rbac_matrix');
