<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterRequest;
use App\Mail\NewsletterWelcome;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Newsletter signup, replacing the n8n webhook (§3).
 *
 * The response is deliberately the same whether the address is new or already
 * subscribed — "You're subscribed" either way. Telling a stranger which
 * addresses are already on the list would turn this form into a membership
 * oracle, and the visitor does not care about the distinction.
 */
final class NewsletterController extends Controller
{
    public function store(NewsletterRequest $request): JsonResponse|RedirectResponse
    {
        $email = mb_strtolower($request->string('email')->trim()->value());

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();
        $isNew = false;

        if (! $subscriber instanceof NewsletterSubscriber) {
            $subscriber = NewsletterSubscriber::create([
                'email' => $email,
                'subscribed_at' => now(),
                'source' => $request->input('source', 'website'),
                'ip_address' => $request->ip(),
            ]);
            $isNew = true;
        } elseif (! $subscriber->isSubscribed()) {
            // Previously opted out, now back. That is a genuine signup again.
            $subscriber->resubscribe();
            $isNew = true;
        }

        // Only on a real signup: mailing an existing subscriber every time they
        // resubmit the form is how a list gets reported as spam.
        if ($isNew) {
            Mail::to($subscriber->email)->send(new NewsletterWelcome($subscriber));

            Log::info('Newsletter signup', ['newsletter_subscriber_id' => $subscriber->id]);
        }

        $confirmation = "You're subscribed. Welcome to the standard.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $confirmation]);
        }

        return back()->with('newsletter_status', $confirmation);
    }
}
