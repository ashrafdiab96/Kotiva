<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\PaymentMethod;
use App\Filament\InitialsAvatarProvider;
use App\Http\Controllers\CheckoutController;
use App\Models\Admin;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\Payments\PaymentGateways;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 6 hardening (§8, §12).
 *
 * The brief names four endpoints to rate-limit. Contact and newsletter were
 * already proven in ContactAndNewsletterTest; these prove the two that guard
 * stock and orders, where an unthrottled script could reserve the whole
 * catalog into abandoned carts or hammer order placement.
 */
final class HardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function adding_to_the_cart_is_rate_limited(): void
    {
        // 60 a minute. Most of these fail validation (no such product) —
        // that is fine: a rejected request still counts, which is the point.
        for ($i = 0; $i < 60; $i++) {
            $this->assertNotSame(
                429,
                $this->postJson('/cart/items', ['product_id' => 999999, 'qty' => 1])->status(),
                'throttled too early, at request '.($i + 1)
            );
        }

        $this->postJson('/cart/items', ['product_id' => 999999, 'qty' => 1])->assertStatus(429);
    }

    #[Test]
    public function placing_an_order_is_rate_limited_harder(): void
    {
        // 10 a minute. Without a checkout session each attempt is turned away,
        // but still counted.
        for ($i = 0; $i < 10; $i++) {
            $this->assertNotSame(429, $this->post('/checkout/payment')->status(), 'request '.($i + 1));
        }

        $this->post('/checkout/payment')->assertStatus(429);
    }

    #[Test]
    public function the_checkout_reaches_payment_gateways_only_through_the_registry(): void
    {
        // The seam §6.6 depends on. If the controller ever takes a concrete
        // gateway again, adding a second one silently needs controller surgery.
        $types = array_map(
            fn (\ReflectionParameter $p): string => (string) $p->getType(),
            (new \ReflectionMethod(CheckoutController::class, '__construct'))->getParameters()
        );

        $this->assertContains(PaymentGateways::class, $types);
        $this->assertNotContains(CashOnDeliveryGateway::class, $types);

        $gateway = app(PaymentGateways::class)->for(PaymentMethod::CashOnDelivery);
        $this->assertSame('cod', $gateway->method());
    }

    #[Test]
    public function the_dashboard_loads_nothing_from_third_party_hosts(): void
    {
        // Filament's defaults fetch Inter from fonts.bunny.net and each avatar
        // from ui-avatars.com — the latter sending every admin's name to a
        // third party on every page. Found by a browser run, not by reading.
        $admin = Admin::create([
            'name' => 'Nora Al Saud',
            'email' => 'nora@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => AdminRole::SuperAdmin,
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        $html = (string) $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('ui-avatars.com', $html);
        $this->assertStringContainsString('css/admin-font.css', $html);

        // The avatar is drawn locally, with the right initials.
        $avatar = app(InitialsAvatarProvider::class)->get($admin);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $avatar);
        $this->assertStringContainsString('>NA<', base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))));
    }

    #[Test]
    public function the_published_example_admin_password_is_refused_in_production(): void
    {
        config([
            'kotiva.admin.email' => 'owner@kotiva.test',
            'kotiva.admin.password' => AdminSeeder::EXAMPLE_PASSWORD,
        ]);
        // isProduction() reads the container's `env` binding.
        $this->app['env'] = 'production';

        try {
            // Called directly: `$this->seed()` runs db:seed, whose own
            // production guard stops at an interactive "are you sure?" before
            // this seeder is ever reached. The check under test is ours.
            app(AdminSeeder::class)->run();
            $this->fail('seeding the example password in production must throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('example value', $e->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, Admin::query()->count(), 'no admin may be created with a public password');
    }

    #[Test]
    public function the_example_password_still_works_for_local_setup(): void
    {
        config([
            'kotiva.admin.email' => 'owner@kotiva.test',
            'kotiva.admin.password' => AdminSeeder::EXAMPLE_PASSWORD,
        ]);

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, Admin::query()->count());
    }

    #[Test]
    public function re_seeding_never_resets_a_changed_admin_password(): void
    {
        config(['kotiva.admin.email' => 'owner@kotiva.test', 'kotiva.admin.password' => 'first-password-123']);
        $this->seed(AdminSeeder::class);

        $admin = Admin::query()->sole();
        $admin->update(['password' => 'changed-by-the-client']);
        $hash = $admin->refresh()->password;

        $this->seed(AdminSeeder::class);

        $this->assertSame($hash, $admin->refresh()->password);
    }
}
