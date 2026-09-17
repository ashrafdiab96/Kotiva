<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What counts as revenue (§7.1, and see DECISIONS D-29).
 *
 * The brief says "delivered + confirmed". The implementation counts four
 * statuses, because read literally the brief would make committed money vanish
 * while an order sits in processing or shipped and reappear on delivery — the
 * reported total would drop exactly when the shop was busiest at packing.
 *
 * That interpretation lived only in a docblock until now. These tests pin it,
 * so changing the set is a deliberate act with a failing test attached rather
 * than a quiet edit to a match expression.
 */
final class OrderStatusRevenueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exactly_four_statuses_count_as_revenue(): void
    {
        $this->assertSame(
            ['confirmed', 'processing', 'shipped', 'delivered'],
            OrderStatus::revenueStatuses(),
        );
    }

    #[Test]
    public function money_not_yet_accepted_or_already_returned_is_excluded(): void
    {
        // Pending is not yet accepted; cancelled returned the stock; refunded
        // returned the money. None of the three is revenue.
        $this->assertFalse(OrderStatus::Pending->countsAsRevenue());
        $this->assertFalse(OrderStatus::Cancelled->countsAsRevenue());
        $this->assertFalse(OrderStatus::Refunded->countsAsRevenue());

        $this->assertTrue(OrderStatus::Confirmed->countsAsRevenue());
        $this->assertTrue(OrderStatus::Processing->countsAsRevenue());
        $this->assertTrue(OrderStatus::Shipped->countsAsRevenue());
        $this->assertTrue(OrderStatus::Delivered->countsAsRevenue());
    }

    #[Test]
    public function the_enum_and_the_query_scope_never_disagree(): void
    {
        // Both must derive from one source; a second copy would keep working
        // while quietly diverging.
        $expected = array_values(array_map(
            fn (OrderStatus $s): string => $s->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s): bool => $s->countsAsRevenue())
        ));

        $this->assertSame($expected, OrderStatus::revenueStatuses());
    }

    #[Test]
    public function the_revenue_scope_selects_only_those_orders(): void
    {
        $customer = Customer::factory()->create();

        foreach (OrderStatus::cases() as $status) {
            Order::factory()->for($customer)->status($status)->create(['grand_total' => '100.00']);
        }

        $selected = Order::query()->revenue()->pluck('status')
            ->map(fn (OrderStatus $s): string => $s->value)
            ->sort()
            ->values()
            ->all();

        $expected = OrderStatus::revenueStatuses();
        sort($expected);

        $this->assertSame($expected, $selected);
        $this->assertSame(4, Order::query()->revenue()->count());
    }

    #[Test]
    public function an_order_moving_through_the_pipeline_never_leaves_the_revenue_total(): void
    {
        // The regression D-29 exists to prevent: the total must not dip as an
        // order progresses from confirmed to delivered.
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->status(OrderStatus::Confirmed)->create(['grand_total' => '250.00']);

        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $order->forceFill(['status' => $status])->save();

            $this->assertSame(
                1,
                Order::query()->revenue()->count(),
                'revenue dropped while the order was '.$status->value
            );
        }
    }
}
