<?php

beforeEach(function () {
    $this->markTestSkipped('Breeze/Inertia session UI removed. Authentication is covered by JWT API tests.');
});

use App\Events\PasswordResetLinkRequested;
use App\Models\User;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\mock;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();
    Event::fake([PasswordResetLinkRequested::class]);

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Event::assertDispatched(PasswordResetLinkRequested::class, function ($event) use ($user) {
        return $event->user->is($user) && $event->token !== '';
    });
    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('reset password link is published for notification service', function () {
    $user = User::factory()->create();

    $producer = mock(AuthNotificationProducer::class);
    $producer->shouldReceive('sendPasswordReset')
        ->once()
        ->withArgs(function (int|string $userId, string $email, string $name, string $resetUrl, int $expiresIn) use ($user) {
            return (string) $userId === (string) $user->id
                && $email === $user->email
                && $name === $user->name
                && str_contains($resetUrl, '/reset-password/')
                && str_contains($resetUrl, urlencode($user->email))
                && $expiresIn === (int) config('auth.passwords.users.expire');
        });

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors();
});

test('reset password screen can be rendered', function () {
    Event::fake([PasswordResetLinkRequested::class]);

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Event::assertDispatched(PasswordResetLinkRequested::class, function ($event) use ($user) {
        $response = $this->get('/reset-password/'.$event->token);

        $response->assertOk();

        return $event->user->is($user);
    });
});

test('password can be reset with valid token', function () {
    Event::fake([PasswordResetLinkRequested::class]);

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Event::assertDispatched(PasswordResetLinkRequested::class, function ($event) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $event->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return $event->user->is($user);
    });
});
