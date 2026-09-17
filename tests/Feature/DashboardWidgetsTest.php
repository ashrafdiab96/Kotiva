<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\OrdersByStatusOverview;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\RevenueOverview;
use App\Filament\Widgets\TopProductsChart;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The dashboard widgets (§7.1).
 *
 * The dev database has no orders at all, so loading /admin proves only that
 * nothing throws. These tests build the figures from factories and check the
 * arithmetic, the gap-filling and the role gating — the three things a glance
 * at an empty dashboard cannot tell you.
 */
final class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function order(OrderStatus $status, string $total, ?string $placedAt = null): Order
    {
        return Order::factory()
            ->for(Customer::factory())
            ->status($status)
            ->create([
                'grand_total' => $total,
                'placed_at' => $placedAt ?? now(),
            ]);
    }

    /* ── role gating ─────────────────────────────────────────── */

    #[Test]
    public function staff_see_the_order_widgets_but_never_the_revenue_ones(): void
    {
        $this->actingAsAdmin(AdminRole::Staff);

        // Staff exist to progress orders; what the shop earns is not theirs.
        $this->assertFalse(RevenueOverview::canView(), 'staff must not see revenue');
        $this->assertFalse(RevenueChart::canView(), 'staff must not see the revenue chart');
        $this->assertFalse(TopProductsChart::canView(), 'staff must not see sales by product');
        $this->assertFalse(LowStockProducts::canView(), 'staff do not manage the catalog');

        $this->assertTrue(OrdersByStatusOverview::canView());
        $this->assertTrue(LatestOrders::canView());
    }

    #[Test]
    public function a_manager_sees_every_widget(): void
    {
        $this->actingAsAdmin(AdminRole::Manager);

        foreach ([RevenueOverview::class, RevenueChart::class, TopProductsChart::class, LowStockProducts::class, OrdersByStatusOverview::class, LatestOrders::class] as $widget) {
            $this->assertTrue($widget::canView(), $widget.' should be visible to a manager');
        }
    }

    #[Test]
    public function a_guest_sees_no_widget_at_all(): void
    {
        // canView() is also what Filament's CanAuthorizeAccess uses to 403 a
        // direct hit, so it must refuse a request with no admin on it.
        $this->assertFalse(RevenueOverview::canView());
        $this->assertFalse(LatestOrders::canView());
    }

    /* ── the figures ─────────────────────────────────────────── */

    #[Test]
    public function revenue_counts_only_revenue_statuses(): void
    {
        $this->actingAsAdmin();

        $this->order(OrderStatus::Confirmed, '100.00');
        $this->order(OrderStatus::Delivered, '250.00');
        $this->order(OrderStatus::Processing, '50.00');
        // Neither of these is money the shop has.
        $this->order(OrderStatus::Pending, '999.00');
        $this->order(OrderStatus::Cancelled, '999.00');

        $series = RevenueChart::series(30);
        $total = array_sum(array_map(fn (array $row): float => $row['revenue'], $series));

        $this->assertSame(400.0, $total);
    }

    #[Test]
    public function the_daily_series_fills_days_with_no_orders(): void
    {
        $this->actingAsAdmin();

        $this->order(OrderStatus::Delivered, '120.00', now()->subDays(3)->toDateTimeString());

        $series = RevenueChart::series(7);

        // Seven rows for seven days: a quiet day must appear as zero, not be
        // omitted, or the line joins across the gap and reads as steady trade.
        $this->assertCount(7, $series);
        $this->assertSame(120.0, array_sum(array_map(fn (array $r): float => $r['revenue'], $series)));

        $zeroDays = array_filter($series, fn (array $r): bool => $r['revenue'] === 0.0);
        $this->assertCount(6, $zeroDays);
    }

    #[Test]
    public function the_sparkline_returns_one_point_per_day(): void
    {
        $this->actingAsAdmin();

        $this->assertCount(7, RevenueOverview::dailySeries(7));
        $this->assertCount(30, RevenueOverview::dailySeries(30));
    }

    #[Test]
    public function todays_revenue_excludes_yesterdays_orders(): void
    {
        $this->actingAsAdmin();

        $this->order(OrderStatus::Delivered, '80.00', now()->subDay()->toDateTimeString());
        $this->order(OrderStatus::Delivered, '40.00', now()->toDateTimeString());

        $today = RevenueChart::series(1);

        $this->assertCount(1, $today);
        $this->assertSame(40.0, $today[0]['revenue']);
        $this->assertSame(1, $today[0]['orders']);
    }

    /* ── top products ────────────────────────────────────────── */

    #[Test]
    public function top_products_survive_the_product_being_deleted(): void
    {
        $this->actingAsAdmin();

        $order = $this->order(OrderStatus::Delivered, '300.00');
        $product = Product::factory()->create();

        OrderItem::factory()->for($order)->for($product)->create([
            'sku_snapshot' => 'KOT-LIVE',
            'name_snapshot' => 'Still In Catalog',
            'qty' => 3,
        ]);

        // A line whose product has since been removed: product_id is null, but
        // the sale happened and must still be counted.
        OrderItem::factory()->for($order)->orphaned()->create([
            'sku_snapshot' => 'KOT-GONE',
            'name_snapshot' => 'Discontinued Serum',
            'qty' => 7,
        ]);

        $top = TopProductsChart::topProducts();

        $this->assertCount(2, $top);
        $this->assertSame('KOT-GONE', $top[0]['sku'], 'the discontinued product sold the most');
        $this->assertSame(7, $top[0]['qty']);
        $this->assertSame('Discontinued Serum', $top[0]['name']);
        $this->assertSame(3, $top[1]['qty']);
    }

    #[Test]
    public function top_products_ignores_cancelled_orders(): void
    {
        $this->actingAsAdmin();

        $cancelled = $this->order(OrderStatus::Cancelled, '300.00');
        OrderItem::factory()->for($cancelled)->orphaned()->create([
            'sku_snapshot' => 'KOT-VOID',
            'name_snapshot' => 'Never Sold',
            'qty' => 99,
        ]);

        $this->assertSame([], TopProductsChart::topProducts());
    }

    #[Test]
    public function top_products_sums_the_same_sku_across_orders(): void
    {
        $this->actingAsAdmin();

        foreach ([2, 5] as $qty) {
            $order = $this->order(OrderStatus::Delivered, '100.00');
            OrderItem::factory()->for($order)->orphaned()->create([
                'sku_snapshot' => 'KOT-SAME',
                'name_snapshot' => 'Repeat Buy',
                'qty' => $qty,
            ]);
        }

        $top = TopProductsChart::topProducts();

        $this->assertCount(1, $top);
        $this->assertSame(7, $top[0]['qty']);
    }

    /* ── low stock ───────────────────────────────────────────── */

    #[Test]
    public function the_low_stock_table_includes_sold_out_and_excludes_healthy_stock(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['sku' => 'KOT-OK', 'stock_qty' => 50, 'low_stock_threshold' => 5]);
        Product::factory()->create(['sku' => 'KOT-LOW', 'stock_qty' => 3, 'low_stock_threshold' => 5]);
        Product::factory()->create(['sku' => 'KOT-OUT', 'stock_qty' => 0, 'low_stock_threshold' => 5]);
        // Inactive products are not on sale, so they are not "running low".
        Product::factory()->inactive()->create(['sku' => 'KOT-OFF', 'stock_qty' => 1, 'low_stock_threshold' => 5]);

        $skus = LowStockProducts::query()->pluck('sku')->sort()->values()->all();

        $this->assertSame(['KOT-LOW', 'KOT-OUT'], $skus);
    }

    /* ── latest orders ───────────────────────────────────────── */

    #[Test]
    public function the_latest_orders_widget_shows_ten_newest_first(): void
    {
        $this->actingAsAdmin();

        for ($i = 0; $i < 12; $i++) {
            $this->order(OrderStatus::Pending, '100.00', now()->subMinutes($i)->toDateTimeString());
        }

        $orders = LatestOrders::query()->get();

        $this->assertCount(10, $orders);
        $this->assertTrue(
            $orders->first()->placed_at->greaterThan($orders->last()->placed_at),
            'newest must be first'
        );
    }

    /* ── the page itself ─────────────────────────────────────── */

    /**
     * @return list<array{string}>
     */
    public static function everyRole(): array
    {
        return [['super_admin'], ['manager'], ['staff']];
    }

    #[Test]
    #[DataProvider('everyRole')]
    public function the_dashboard_renders_for_every_role(string $role): void
    {
        // One authentication per test method, deliberately. Filament's
        // AuthenticateSession middleware stores password_hash_admin in the
        // session and logs the request out the moment it sees a different
        // user's hash, so looping logins inside a single method 302s on the
        // second role — which looks exactly like a permissions bug and is not
        // one. The rest of the suite follows the same one-login-per-test rule.
        $this->actingAsAdmin(AdminRole::from($role));

        $this->assertSame(200, $this->get('/admin')->status(), $role.' should reach the dashboard');
    }

    #[Test]
    public function the_dashboard_renders_with_real_data_behind_it(): void
    {
        $this->actingAsAdmin();

        $order = $this->order(OrderStatus::Delivered, '325.00');
        OrderItem::factory()->for($order)->orphaned()->create(['qty' => 2]);
        Product::factory()->create(['stock_qty' => 1, 'low_stock_threshold' => 5]);

        $this->assertSame(200, $this->get('/admin')->status());
    }
}
