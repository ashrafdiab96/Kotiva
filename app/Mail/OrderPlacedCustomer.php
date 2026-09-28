<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Models\ShippingRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's order confirmation.
 *
 * Queued, because placing an order must not wait on an SMTP handshake — a slow
 * mail server would otherwise hold the shopper on a spinning Place Order button
 * after their order had already been committed.
 */
final class OrderPlacedCustomer extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order '.$this->order->order_no.' confirmed — KOTIVA',
            replyTo: [(string) config('kotiva.mail.store_email')],
        );
    }

    public function content(): Content
    {
        $rate = $this->order->shippingZone?->activeRate;

        return new Content(
            view: 'emails.order-placed-customer',
            with: [
                'order' => $this->order->loadMissing(['items', 'customer', 'shippingZone.activeRate']),
                'deliveryEstimate' => $rate instanceof ShippingRate ? $rate->estimateLabel() : null,
            ],
        );
    }
}
