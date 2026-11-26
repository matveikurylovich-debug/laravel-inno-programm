<?php

declare(strict_types=1);

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int|string $orderId,
        public readonly string $reason,
        public readonly float|int|null $refundedAmount = null,
        public readonly ?string $currency = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Заказ #{$this->orderId} отменён",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order.cancelled',
        );
    }
}
