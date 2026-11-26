<?php

declare(strict_types=1);

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expiresIn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Восстановление доступа к аккаунту',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password_reset',
        );
    }
}
