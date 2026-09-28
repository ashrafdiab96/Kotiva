<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The shop's own new-order notification.
 *
 * Carries everything needed to pick and dispatch without opening the
 * dashboard, because the first thing someone does with this email is read it
 * on a phone.
 */
final class OrderPlacedAdmin extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'New order %s — %s %s',
                $this->order->order_no,
                $this->order->currency,
                $this->order->grand_total
            ),
            // Replying goes to the buyer, which is nearly always the intent.
            replyTo: [$this->order->customer->email],
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['items', 'customer']);

        return new Content(
            view: 'emails.order-placed-admin',
            with: [
                'order' => $order,
                // Excludes this one, so "repeat buyer" means genuinely before.
                'previousOrders' => $order->customer
                    ->orders()
                    ->whereKeyNot($order->getKey())
                    ->count(),
            ],
        );
    }
}
