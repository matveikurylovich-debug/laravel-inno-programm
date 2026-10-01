<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string|int $orderId,
        public readonly ?string $estimatedDelivery = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Заказ #{$this->orderId} подтвержден",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order.confirmed',
        );
    }
}
