<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an order reaches a status the customer cares about: shipped,
 * delivered or cancelled (§6.8).
 *
 * The copy is chosen per status rather than printing the status name into a
 * generic sentence — "Your order is now Cancelled" reads like a system log,
 * not like a message from a brand.
 */
final class OrderStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $status,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->headline().' — order '.$this->order->order_no,
            replyTo: [(string) config('kotiva.mail.store_email')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-updated',
            with: [
                'order' => $this->order->loadMissing(['items', 'customer']),
                'headline' => $this->headline(),
                /*
                 | Deliberately NOT called "message". Laravel injects its own
                 | $message (an Illuminate\Mail\Message) into every mail view,
                 | which shadows any view data of that name — the template then
                 | tried to escape a Message object and threw a ViewException.
                 */
                'bodyCopy' => $this->bodyCopy(),
            ],
        );
    }

    private function headline(): string
    {
        return match ($this->status) {
            OrderStatus::Shipped => 'Your order is on its way',
            OrderStatus::Delivered => 'Your order has arrived',
            OrderStatus::Cancelled => 'Your order has been cancelled',
            default => 'Order update',
        };
    }

    private function bodyCopy(): string
    {
        return match ($this->status) {
            OrderStatus::Shipped => 'Good news — your order has left us and is on its way to you. '
                .'The courier will call the number on your order before delivering.',
            OrderStatus::Delivered => 'Your order has been delivered. We hope you enjoy it — '
                .'and if anything is not right, just reply to this email.',
            OrderStatus::Cancelled => 'Your order has been cancelled, as requested.',
            default => 'There is an update on your order.',
        };
    }
}
