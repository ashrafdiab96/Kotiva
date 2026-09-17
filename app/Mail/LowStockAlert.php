<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the shop a product is running out, while there is still time to act.
 */
final class LowStockAlert extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly int $remaining,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->remaining <= 0
                ? sprintf('SOLD OUT: %s (%s)', $this->product->displayName(), $this->product->sku)
                : sprintf(
                    'Low stock: %s — %d left (%s)',
                    $this->product->displayName(),
                    $this->remaining,
                    $this->product->sku
                ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
            with: [
                'product' => $this->product,
                'remaining' => $this->remaining,
            ],
        );
    }
}
