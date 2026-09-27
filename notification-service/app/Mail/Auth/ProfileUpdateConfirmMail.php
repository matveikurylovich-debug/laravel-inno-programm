<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileUpdateConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $field = 'профиль'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Подтверждение изменения данных профиля',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.profile_update',
        );
    }
}