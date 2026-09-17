<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcomes a new newsletter subscriber.
 *
 * Sent only on a genuine new signup or a re-subscribe — never when an existing
 * subscriber submits the form again, which would read as spam to the one group
 * most likely to report it.
 */
final class NewsletterWelcome extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to KOTIVA',
            replyTo: [(string) config('kotiva.mail.store_email')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-welcome',
            with: ['subscriber' => $this->subscriber],
        );
    }
}
