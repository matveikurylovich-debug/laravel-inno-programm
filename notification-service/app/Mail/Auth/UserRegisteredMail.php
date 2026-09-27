<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public ?string $verificationUrl = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Добро пожаловать! Подтверждение регистрации',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.registered',
        );
    }
}