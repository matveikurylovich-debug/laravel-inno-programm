<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordResetLinkRequested;
use App\Messaging\Producers\AuthEventsProducer;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class PublishPasswordResetLinkRequested implements ShouldBeEncrypted, ShouldQueue
{
    public string $queue = AuthEventsProducer::QUEUE;

    public int $tries = 10;

    public function __construct(
        protected AuthEventsProducer $producer
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('auth.passwords.users.expire'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 3, 10, 30];
    }

    public function handle(PasswordResetLinkRequested $event): void
    {
        $user = $event->user;
        $frontendBase = $event->frontendBase !== ''
            ? $event->frontendBase
            : (string) config('app.frontend_url');
        $resetUrl = rtrim($frontendBase, '/').'/reset-password/'.$event->token.'?email='.urlencode($user->getEmailForPasswordReset());

        $this->producer->sendPasswordReset(
            userId: $user->id,
            email: $user->email,
            name: $user->name,
            resetUrl: $resetUrl,
            expiresIn: (int) config('auth.passwords.users.expire'),
        );
    }
}
