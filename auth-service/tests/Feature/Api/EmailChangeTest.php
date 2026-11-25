<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_patch_cannot_change_email_without_the_confirmation_flow(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $token = app(JwtService::class)->createAccessToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => 'new@example.com',
                'phone' => '+375291112233',
            ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_email_changes_only_after_the_signed_link(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $token = app(JwtService::class)->createAccessToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/profile/email', [
                'email' => 'new@example.com',
            ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/profile/email', [
                'email' => 'new@example.com',
                'current_password' => 'password',
            ])->assertOk();

        $this->assertSame('old@example.com', $user->fresh()->email);

        $confirm = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), [
            'user' => $user->id,
            'email' => 'new@example.com',
        ]);
        $this->get($confirm)->assertOk();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }
}
