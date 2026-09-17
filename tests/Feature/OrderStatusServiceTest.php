<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementReason;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Services\OrderStatusService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The order state machine, and the thing that makes it more than a column:
 * cancelling an order must put its units back on the shelf.
 */
final class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderStatusService::class);
    }

    private function product(int $stock = 10): Product
    {
        $product = Product::factory()->withStock(0)->create();
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    /**
     * An order as it exists after checkout: units reserved then fulfilled, so
     * stock is already down by the quantity sold.
     */
    private function soldOrder(int $stock = 10, int $qty = 3): array
    {
        $product = $this->product($stock);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $item = OrderItem::factory()->for($order)->for($product)->create([
            'qty' => $qty,
            'unit_price' => '100.00',
            'line_total' => bcmul('100.00', (string) $qty, 2),
        ]);

        $stockService = app(StockService::class);
        $stockService->reserve($product, $qty);
        $stockService->fulfil($product->fresh(), $qty, $order);

        return [$order->fresh(), $product->fresh(), $item];
    }

    private function assertLedgerReconciles(Product $product): void
    {
        $sum = (int) ProductStockMovement::query()->where('product_id', $product->getKey())->sum('delta');

        $this->assertSame((int) $product->fresh()->stock_qty, $sum);
    }

    #[Test]
    public function a_permitted_transition_stamps_its_timestamp_and_records_history(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        // A real admin: admin_id is a foreign key now, so an invented id would
        // be rejected — which is the constraint working, not a test problem.
        $admin = Admin::create([
            'name' => 'Ops',
            'email' => 'ops@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => AdminRole::Manager,
        ]);

        $updated = $this->service->transition($order, OrderStatus::Confirmed, 'Called the customer', adminId: $admin->id);

        $this->assertSame(OrderStatus::Confirmed, $updated->status);
        $this->assertNotNull($updated->confirmed_at);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Confirmed->value,
            'admin_id' => $admin->id,
            'note' => 'Called the customer',
        ]);
    }

    #[Test]
    public function the_documented_happy_path_runs_end_to_end(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $next) {
            $order = $this->service->transition($order, $next);
            $this->assertSame($next, $order->status);
        }

        $this->assertNotNull($order->shipped_at);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame(4, $order->statusHistories()->count());
    }

    #[Test]
    public function a_forbidden_transition_is_refused_and_changes_nothing(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        try {
            $this->service->transition($order, OrderStatus::Shipped);
            $this->fail('expected InvalidOrderTransition');
        } catch (InvalidOrderTransition $e) {
            $this->assertStringContainsString('cannot become Shipped', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    #[Test]
    public function terminal_statuses_cannot_move_anywhere(): void
    {
        foreach ([OrderStatus::Cancelled, OrderStatus::Refunded] as $terminal) {
            $this->assertTrue($terminal->isTerminal());
            $this->assertSame([], $terminal->allowedTransitions());

            $order = Order::factory()->create(['status' => $terminal]);

            $this->expectException(InvalidOrderTransition::class);
            $this->service->transition($order, OrderStatus::Processing);
        }
    }

    #[Test]
    public function a_delivered_order_may_be_refunded(): void
    {
        // Otherwise `refunded` would be a status no order could ever reach.
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->assertSame(
            OrderStatus::Refunded,
            $this->service->transition($order, OrderStatus::Refunded)->status
        );
    }

    /* ── the part that touches real stock ─────────────────── */

    #[Test]
    public function cancelling_returns_the_units_to_the_catalog(): void
    {
        [$order, $product] = $this->soldOrder(stock: 10, qty: 3);

        // Sold: 10 - 3.
        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        $this->service->transition($order, OrderStatus::Cancelled, 'Customer changed their mind');

        $this->assertSame(10, (int) $product->fresh()->stock_qty, 'cancelling must restock');
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertLedgerReconciles($product);

        /*
         | Both fulfilment and cancellation write an OrderReleased movement
         | against the order — fulfilment to close the cart's reservation,
         | cancellation to put the units back. Summing by reason and reference
         | alone therefore counts both, so the cancellation is identified by
         | the note that names it.
         */
        $restocked = ProductStockMovement::query()
            ->where('product_id', $product->id)
            ->where('reason', StockMovementReason::OrderReleased)
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->where('note', 'like', '%cancelled%')
            ->sum('delta');

        $this->assertSame(3, (int) $restocked, 'the restock is attributed to the cancellation');

        // And the order's whole history nets to zero: nothing was sold.
        $net = ProductStockMovement::query()
            ->where('product_id', $product->id)
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->sum('delta');

        $this->assertSame(3, (int) $net, 'fulfil(-3) + close(+3) + cancel(+3)');
    }

    #[Test]
    public function cancelling_is_allowed_from_pending_and_confirmed_only(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Confirmed] as $from) {
            $this->assertTrue($from->canTransitionTo(OrderStatus::Cancelled));
        }

        foreach ([OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered] as $from) {
            $this->assertFalse(
                $from->canTransitionTo(OrderStatus::Cancelled),
                $from->value.' should not be cancellable'
            );
        }
    }

    #[Test]
    public function a_refused_cancellation_does_not_restock(): void
    {
        [$order, $product] = $this->soldOrder(stock: 10, qty: 3);
        $order = $this->service->transition($order, OrderStatus::Confirmed);
        $order = $this->service->transition($order, OrderStatus::Processing);

        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        try {
            $this->service->transition($order, OrderStatus::Cancelled);
            $this->fail('expected InvalidOrderTransition');
        } catch (InvalidOrderTransition) {
            // expected
        }

        // The guard runs before any stock is touched.
        $this->assertSame(7, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function cancelling_an_order_whose_product_was_deleted_still_succeeds(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->orphaned()->create(['qty' => 2]);

        $updated = $this->service->transition($order, OrderStatus::Cancelled);

        $this->assertSame(OrderStatus::Cancelled, $updated->status);
    }

    /* ── payment, kept separate from status ───────────────── */

    #[Test]
    public function marking_paid_is_independent_of_the_order_status(): void
    {
        // A COD order ships unpaid; "shipped" must never imply "paid".
        $order = Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $admin = Admin::create([
            'name' => 'Ops',
            'email' => 'paid@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => AdminRole::Staff,
        ]);

        $updated = $this->service->markPaid($order, adminId: $admin->id);

        $this->assertSame(PaymentStatus::Paid, $updated->payment_status);
        $this->assertSame(OrderStatus::Shipped, $updated->status, 'the order status is untouched');
    }

    #[Test]
    public function marking_paid_twice_does_not_duplicate_history(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::Unpaid]);

        $this->service->markPaid($order);
        $before = $order->statusHistories()->count();

        $this->service->markPaid($order->fresh());

        $this->assertSame($before, $order->fresh()->statusHistories()->count());
    }

    /* ── events ───────────────────────────────────────────── */

    #[Test]
    public function a_status_change_announces_itself(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $this->service->transition($order, OrderStatus::Confirmed);

        Event::assertDispatched(
            OrderStatusChanged::class,
            fn (OrderStatusChanged $e): bool => $e->from === OrderStatus::Pending
                && $e->to === OrderStatus::Confirmed
        );
    }

    #[Test]
    public function only_the_statuses_the_customer_cares_about_notify_them(): void
    {
        $this->assertTrue(OrderStatus::Shipped->notifiesCustomer());
        $this->assertTrue(OrderStatus::Delivered->notifiesCustomer());
        $this->assertTrue(OrderStatus::Cancelled->notifiesCustomer());

        // Internal steps are not the customer's business.
        $this->assertFalse(OrderStatus::Confirmed->notifiesCustomer());
        $this->assertFalse(OrderStatus::Processing->notifiesCustomer());
    }

    #[Test]
    public function the_dashboard_is_only_offered_transitions_that_will_succeed(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $offered = $this->service->availableTransitions($order);

        $this->assertSame([OrderStatus::Confirmed, OrderStatus::Cancelled], $offered);

        foreach ($offered as $target) {
            $this->assertTrue($order->status->canTransitionTo($target));
        }
    }
}
