<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The storefront announcement bar (§7.6).
 *
 * It is driven entirely by a setting an admin edits, which makes it the one
 * piece of storefront chrome that can be switched on by someone who cannot
 * test it — so the off, blank and escaping cases matter as much as the on one.
 */
final class AnnouncementBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
    }

    private function enable(string $text): void
    {
        Setting::put('announcement_bar', ['enabled' => true, 'text' => $text]);
    }

    #[Test]
    public function it_renders_above_the_nav_when_switched_on(): void
    {
        $this->enable('Free delivery over SAR 300');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Free delivery over SAR 300', escape: false);
        $response->assertSee('kotiva-announce', escape: false);
        // The class is what retunes --nav-h, so without it the bar would
        // overlap the page rather than push it down.
        $response->assertSee('has-announcement', escape: false);
    }

    #[Test]
    public function it_is_absent_when_switched_off(): void
    {
        Setting::put('announcement_bar', ['enabled' => false, 'text' => 'Not shown']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Not shown', escape: false);
        $response->assertDontSee('kotiva-announce', escape: false);
        $response->assertDontSee('has-announcement', escape: false);
    }

    #[Test]
    public function it_is_absent_by_default_on_a_fresh_install(): void
    {
        // No setting row at all: the layout must fall back to "off" rather
        // than rendering an empty bar.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('kotiva-announce', escape: false);
    }

    #[Test]
    public function the_switch_being_on_with_no_text_renders_nothing(): void
    {
        $this->enable('   ');

        $response = $this->get('/');

        $response->assertOk();
        // An empty bar would still push the whole page down by its height.
        $response->assertDontSee('kotiva-announce', escape: false);
        $response->assertDontSee('has-announcement', escape: false);
    }

    #[Test]
    public function the_message_is_escaped(): void
    {
        $this->enable('<script>alert(1)</script>');

        $response = $this->get('/');

        $response->assertOk();
        // An admin is trusted, but the bar is on every page — unescaped markup
        // here would be stored XSS across the whole storefront.
        $response->assertDontSee('<script>alert(1)</script>', escape: false);
        $response->assertSee('&lt;script&gt;', escape: false);
    }

    #[Test]
    public function it_appears_on_every_storefront_page_not_only_the_home_page(): void
    {
        $this->enable('Free delivery over SAR 300');

        foreach (['/', '/shop', '/about', '/contact'] as $path) {
            $this->get($path)->assertSee('kotiva-announce', escape: false);
        }
    }

    #[Test]
    public function the_skip_link_stays_the_first_focusable_element(): void
    {
        $this->enable('Free delivery over SAR 300');

        $html = $this->get('/')->getContent();

        $skip = strpos((string) $html, 'skip-link');
        $bar = strpos((string) $html, 'kotiva-announce');

        $this->assertNotFalse($skip);
        $this->assertNotFalse($bar);
        // Keyboard users must reach "Skip to content" before the banner.
        $this->assertLessThan($bar, $skip);
    }
}
