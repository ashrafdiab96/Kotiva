<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who can reach what in the dashboard (§7).
 *
 * These exist because curl cannot log in here: Filament v3's login is a
 * Livewire component, so POSTing to /admin/login returns 405 and every page
 * just 302s to the login screen. That looks identical whether the panel works
 * perfectly or not at all — acting as a real Admin on the `admin` guard is the
 * only way to tell the difference.
 *
 * Role gating is the substance: hiding a resource from the navigation is
 * cosmetic, and a staff account that can still reach /admin/products by typing
 * the URL is not restricted at all.
 */
final class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(AdminRole $role, bool $active = true): Admin
    {
        return Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    #[Test]
    public function the_panel_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/categories')->assertRedirect('/admin/login');
        $this->get('/admin/orders')->assertRedirect('/admin/login');
    }

    #[Test]
    public function the_login_screen_is_reachable_and_branded(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('KOTIVA', escape: false);
    }

    #[Test]
    public function a_super_admin_reaches_everything(): void
    {
        $this->actingAs($this->admin(AdminRole::SuperAdmin), 'admin');

        foreach ([
            '/admin',
            '/admin/categories',
            '/admin/products',
            '/admin/orders',
            '/admin/customers',
            '/admin/product-stock-movements',
            '/admin/shipping-zones',
            '/admin/shipping-cities',
            '/admin/contact-messages',
            '/admin/newsletter-subscribers',
            '/admin/admins',
        ] as $path) {
            // assertOk() takes no message, so the path is asserted directly —
            // otherwise a failure names only a status code.
            $this->assertSame(200, $this->get($path)->status(), "super admin should reach {$path}");
        }
    }

    #[Test]
    public function a_manager_runs_the_shop_but_cannot_touch_admin_accounts(): void
    {
        $this->actingAs($this->admin(AdminRole::Manager), 'admin');

        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/customers')->assertOk();
        $this->get('/admin/shipping-zones')->assertOk();

        // Admin accounts and settings are the two things that can quietly
        // break the whole shop.
        $this->get('/admin/admins')->assertForbidden();
    }

    #[Test]
    public function staff_see_orders_only(): void
    {
        $this->actingAs($this->admin(AdminRole::Staff), 'admin');

        $this->get('/admin/orders')->assertOk();

        // Typing the URL must be refused, not merely hidden from the nav.
        // Every non-order resource is listed: a gate that covers only the
        // obvious ones leaves the rest open to exactly the same URL guess.
        foreach ([
            '/admin/categories',
            '/admin/products',
            '/admin/product-stock-movements',
            '/admin/customers',
            '/admin/shipping-zones',
            '/admin/shipping-cities',
            '/admin/contact-messages',
            '/admin/newsletter-subscribers',
            '/admin/admins',
        ] as $path) {
            $this->assertSame(403, $this->get($path)->status(), "staff must not reach {$path}");
        }
    }

    #[Test]
    public function a_deactivated_admin_is_locked_out_immediately(): void
    {
        // Otherwise is_active is a label rather than a control.
        $this->actingAs($this->admin(AdminRole::SuperAdmin, active: false), 'admin');

        $this->get('/admin')->assertForbidden();
    }

    #[Test]
    public function the_storefront_is_unaffected_by_an_admin_session(): void
    {
        $this->actingAs($this->admin(AdminRole::SuperAdmin), 'admin');

        $this->get('/')->assertOk();
        $this->get('/shop')->assertOk();
    }

    #[Test]
    public function roles_declare_their_own_boundaries_consistently(): void
    {
        $this->assertTrue(AdminRole::SuperAdmin->canManageSettings());
        $this->assertFalse(AdminRole::Manager->canManageSettings());
        $this->assertFalse(AdminRole::Staff->canManageSettings());

        $this->assertTrue(AdminRole::Manager->canManageCatalog());
        $this->assertFalse(AdminRole::Staff->canManageCatalog());

        // Only a super admin may destroy a record; orders are records.
        $this->assertTrue(AdminRole::SuperAdmin->canDeleteRecords());
        $this->assertFalse(AdminRole::Manager->canDeleteRecords());
    }
}
