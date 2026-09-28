<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Mail\NewsletterWelcome;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §3's replacement for the n8n webhook.
 *
 * The handover README warned that the webhook was operated by the previous
 * agency and not guaranteed to keep running. The property that matters most
 * here is therefore that a customer's message survives even when delivery does
 * not — so several of these assert what happens when mail is misconfigured.
 */
final class ContactAndNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /* ── contact ──────────────────────────────────────────── */

    #[Test]
    public function an_enquiry_is_stored_and_notified(): void
    {
        $this->postJson('/contact', [
            'name' => 'Noura Al Qahtani',
            'email' => 'Noura@Example.com',
            'enquiry_type' => 'wholesale',
            'message' => 'Do you ship to Jeddah?',
        ])->assertOk();

        $enquiry = ContactMessage::query()->firstOrFail();

        $this->assertSame('Noura Al Qahtani', $enquiry->name);
        // Normalised, so the dashboard does not show the same person twice.
        $this->assertSame('noura@example.com', $enquiry->email);
        $this->assertSame('wholesale', $enquiry->enquiry_type);
        $this->assertFalse($enquiry->is_handled);

        Mail::assertQueued(
            ContactMessageReceived::class,
            fn (ContactMessageReceived $mail): bool => $mail->contactMessage->is($enquiry)
        );
    }

    #[Test]
    public function the_enquiry_survives_a_misconfigured_recipient(): void
    {
        // The whole point of storing before sending.
        Setting::put('store_email', 'not-an-email');

        $this->postJson('/contact', [
            'name' => 'Noura',
            'email' => 'noura@example.com',
            'message' => 'Hello',
        ])->assertOk();

        $this->assertSame(1, ContactMessage::query()->count());
        Mail::assertNotQueued(ContactMessageReceived::class);
    }

    #[Test]
    public function an_enquiry_is_validated(): void
    {
        $this->postJson('/contact', ['name' => '', 'email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);

        $this->postJson('/contact', [
            'name' => 'Noura',
            'email' => 'noura@example.com',
            'enquiry_type' => 'not-a-real-type',
        ])->assertStatus(422)->assertJsonValidationErrors('enquiry_type');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    #[Test]
    public function the_enquiry_type_is_optional_because_the_static_form_allowed_it_empty(): void
    {
        $this->postJson('/contact', [
            'name' => 'Noura',
            'email' => 'noura@example.com',
            'message' => 'General question',
        ])->assertOk();

        $this->assertNull(ContactMessage::query()->firstOrFail()->enquiry_type);
    }

    #[Test]
    public function a_form_post_without_javascript_redirects_back(): void
    {
        $this->from('/contact')
            ->post('/contact', ['name' => 'Noura', 'email' => 'noura@example.com'])
            ->assertRedirect('/contact')
            ->assertSessionHas('contact_status');
    }

    /* ── newsletter ───────────────────────────────────────── */

    #[Test]
    public function a_signup_subscribes_and_welcomes(): void
    {
        $this->postJson('/newsletter', ['email' => 'Sara@Example.com'])->assertOk();

        $subscriber = NewsletterSubscriber::query()->firstOrFail();

        $this->assertSame('sara@example.com', $subscriber->email);
        $this->assertTrue($subscriber->isSubscribed());

        Mail::assertQueued(NewsletterWelcome::class);
    }

    #[Test]
    public function signing_up_twice_neither_duplicates_nor_re_welcomes(): void
    {
        $this->postJson('/newsletter', ['email' => 'sara@example.com'])->assertOk();
        Mail::fake();

        $this->postJson('/newsletter', ['email' => 'sara@example.com'])->assertOk();

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        // Mailing an existing subscriber every resubmit is how a list gets
        // reported as spam.
        Mail::assertNotQueued(NewsletterWelcome::class);
    }

    #[Test]
    public function an_existing_subscriber_is_not_told_they_are_already_on_the_list(): void
    {
        NewsletterSubscriber::create(['email' => 'sara@example.com', 'subscribed_at' => now()]);

        // Identical response either way: the form must not become a way to
        // discover which addresses are subscribed.
        $first = $this->postJson('/newsletter', ['email' => 'sara@example.com']);
        $second = $this->postJson('/newsletter', ['email' => 'brand-new@example.com']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('message'), $second->json('message'));
    }

    #[Test]
    public function someone_who_opted_out_can_come_back(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'sara@example.com',
            'subscribed_at' => now()->subYear(),
        ]);
        $subscriber->unsubscribe();

        $this->assertFalse($subscriber->fresh()->isSubscribed());

        $this->postJson('/newsletter', ['email' => 'sara@example.com'])->assertOk();

        $this->assertTrue($subscriber->fresh()->isSubscribed());
        $this->assertSame(1, NewsletterSubscriber::query()->count());
        // A genuine re-signup, so it is welcomed.
        Mail::assertQueued(NewsletterWelcome::class);
    }

    #[Test]
    public function a_signup_is_validated(): void
    {
        $this->postJson('/newsletter', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, NewsletterSubscriber::query()->count());
    }

    #[Test]
    public function both_endpoints_are_rate_limited(): void
    {
        // Unauthenticated endpoints that write rows and send mail.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/newsletter', ['email' => "user{$i}@example.com"])->assertOk();
        }

        $this->postJson('/newsletter', ['email' => 'one-too-many@example.com'])
            ->assertStatus(429);
    }
}
