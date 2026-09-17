<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the shop of a contact-form enquiry.
 *
 * The enquiry is already stored before this is queued, so a mail failure costs
 * a notification rather than the customer's message.
 */
final class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Enquiry: %s — %s', $this->contactMessage->enquiryLabel(), $this->contactMessage->name),
            // Replying goes straight to the person who wrote in.
            replyTo: [$this->contactMessage->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message-received',
            // NOT "message": Laravel injects its own $message into mail views
            // and would shadow it. See emails/layout.blade.php.
            with: ['enquiry' => $this->contactMessage],
        );
    }
}
