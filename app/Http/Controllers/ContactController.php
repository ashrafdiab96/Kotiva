<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Replaces the n8n webhook the static site posted to (§3).
 *
 * The enquiry is STORED FIRST and mailed second. The handover README warned
 * that the webhook was run by the previous agency and not guaranteed to keep
 * running; the lesson taken from that is that a customer's message must not
 * depend on a delivery mechanism succeeding.
 */
final class ContactController extends Controller
{
    public function store(ContactRequest $request): JsonResponse|RedirectResponse
    {
        $enquiry = ContactMessage::create([
            'name' => $request->string('name')->trim()->value(),
            'email' => mb_strtolower($request->string('email')->trim()->value()),
            'enquiry_type' => $request->input('enquiry_type'),
            'message' => $request->input('message'),
            'ip_address' => $request->ip(),
        ]);

        $recipients = $this->recipients();

        if ($recipients === []) {
            Log::warning('Contact enquiry received but no recipient is configured', [
                'contact_message_id' => $enquiry->id,
            ]);
        } else {
            Mail::to($recipients)->send(new ContactMessageReceived($enquiry));
        }

        Log::info('Contact enquiry received', [
            'contact_message_id' => $enquiry->id,
            'enquiry_type' => $enquiry->enquiry_type,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Message sent — we'll be in touch soon.",
            ]);
        }

        return back()->with('contact_status', "Message sent — we'll be in touch soon.");
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $configured = Setting::get('store_email', config('kotiva.mail.store_email'));

        $list = is_array($configured)
            ? $configured
            : array_map('trim', explode(',', (string) $configured));

        return array_values(array_filter(
            $list,
            static fn (mixed $email): bool => is_string($email)
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
