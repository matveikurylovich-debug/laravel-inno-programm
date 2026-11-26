<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

it('requires an access token on gateway verify', function (): void {
    $this->getJson('/api/v1/gateway/verify')->assertUnauthorized();
});

it('accepts a live access token on gateway verify and echoes X-Access-Token', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);
    $token = $this->tokenFor($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/gateway/verify')
        ->assertNoContent()
        ->assertHeader('X-Access-Token', $token);
});
