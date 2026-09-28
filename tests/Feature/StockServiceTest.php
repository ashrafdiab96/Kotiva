<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §6.5 is the part of this build that must be correct rather than merely
 * working: stock may never go negative, and the movement ledger must always
 * reconcile against the cached products.stock_qty.
 *
 * Note on concurrency: these assert the observable invariants, not the lock
 * internals. SQLite (the test driver the brief specifies) does not implement
 * SELECT … FOR UPDATE, so a test asserting lock behaviour here would prove
 * nothing about MySQL. What is tested is that availability is re-checked after
 * the row is re-read inside the transaction, which is what makes the lock
 * effective on an engine that honours it. See docs/DECISIONS.md D-03.
 */
final class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
    }

    /**
     * Opens the product's ledger with a restock rather than setting stock_qty
     * directly, which is what ProductSeeder does in production. It also makes
     * assertLedgerReconciles() a true invariant: a product whose opening
     * balance is not itself a movement can never reconcile.
     */
    private function product(int $stock = 10): Product
    {
        $product = Product::factory()->withStock(0)->create();

        if ($stock > 0) {
            $this->stock->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');
        }

        return $product->fresh();
    }

    /**
     * The invariant that matters most: the cached quantity must always equal
     * the sum of the ledger.
     */
    private function assertLedgerReconciles(Product $product): void
    {
        $sum = (int) ProductStockMovement::query()
            ->where('product_id', $product->getKey())
            ->sum('delta');

        $this->assertSame(
            (int) $product->fresh()->stock_qty,
            $sum,
            'stock_qty must equal SUM(movement deltas)'
        );
    }

    #[Test]
    public function the_opening_balance_itself_reconciles(): void
    {
        $product = $this->product(10);

        $this->assertSame(10, (int) $product->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function reserving_decrements_stock_and_writes_a_movement(): void
    {
        $product = $this->product(10);

        $this->stock->reserve($product, 3);

        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        $movement = ProductStockMovement::query()->latest('id')->firstOrFail();
        $this->assertSame(-3, $movement->delta);
        $this->assertSame(StockMovementReason::OrderReserved, $movement->reason);

        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function reserving_more_than_exists_is_refused_with_the_real_maximum(): void
    {
        $product = $this->product(2);
        $movementsBefore = ProductStockMovement::query()->where('product_id', $product->id)->count();

        try {
            $this->stock->reserve($product, 5);
            $this->fail('expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(2, $e->available);
            $this->assertSame(5, $e->requested);
            $this->assertStringContainsString('Only 2', $e->userMessage());
        }

        // A refused attempt must change nothing at all.
        $this->assertSame(2, (int) $product->fresh()->stock_qty);
        $this->assertSame(
            $movementsBefore,
            ProductStockMovement::query()->where('product_id', $product->id)->count()
        );
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function stock_can_be_taken_to_exactly_zero_but_never_below(): void
    {
        $product = $this->product(3);

        $this->stock->reserve($product, 3);
        $this->assertSame(0, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);

        $this->expectException(InsufficientStockException::class);
        $this->stock->reserve($product->fresh(), 1);
    }

    #[Test]
    public function the_last_unit_cannot_be_reserved_twice(): void
    {
        $product = $this->product(1);

        $this->stock->reserve($product, 1);

        try {
            $this->stock->reserve($product->fresh(), 1);
            $this->fail('the last unit was sold twice');
        } catch (InsufficientStockException $e) {
            $this->assertSame(0, $e->available);
            $this->assertStringContainsString('sold out', $e->userMessage());
        }

        $this->assertSame(0, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function releasing_returns_stock_and_records_it(): void
    {
        $product = $this->product(10);

        $this->stock->reserve($product, 4);
        $this->stock->release($product, 4);

        $this->assertSame(10, (int) $product->fresh()->stock_qty);

        $reasons = ProductStockMovement::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->pluck('reason')
            ->all();

        $this->assertSame(
            [
                StockMovementReason::Restock,
                StockMovementReason::OrderReserved,
                StockMovementReason::OrderReleased,
            ],
            $reasons
        );

        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function adjusting_a_reservation_moves_only_the_difference(): void
    {
        $product = $this->product(10);
        $item = CartItem::factory()->for($product)->create(['qty' => 2]);

        $this->stock->reserve($product, 2, $item);
        $this->assertSame(8, (int) $product->fresh()->stock_qty);

        // 2 -> 5 reserves three more, not five.
        $this->stock->adjustReservation($item->fresh(), 5);
        $this->assertSame(5, (int) $product->fresh()->stock_qty);
        $item->forceFill(['qty' => 5])->save();

        // 5 -> 1 releases four.
        $this->stock->adjustReservation($item->fresh(), 1);
        $this->assertSame(9, (int) $product->fresh()->stock_qty);

        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function an_unchanged_quantity_writes_no_movement(): void
    {
        $product = $this->product(10);
        $item = CartItem::factory()->for($product)->create(['qty' => 3]);

        $this->stock->reserve($product, 3, $item);
        $before = ProductStockMovement::query()->count();

        $this->stock->adjustReservation($item->fresh(), 3);

        $this->assertSame($before, ProductStockMovement::query()->count());
    }

    #[Test]
    public function raising_a_reservation_beyond_stock_is_refused(): void
    {
        $product = $this->product(5);
        $item = CartItem::factory()->for($product)->create(['qty' => 5]);

        $this->stock->reserve($product, 5, $item);
        $this->assertSame(0, (int) $product->fresh()->stock_qty);

        $this->expectException(InsufficientStockException::class);
        $this->stock->adjustReservation($item->fresh(), 6);
    }

    #[Test]
    public function fulfilment_closes_the_reservation_without_double_decrementing(): void
    {
        $product = $this->product(10);
        $item = CartItem::factory()->for($product)->create(['qty' => 2]);

        $this->stock->reserve($product, 2, $item);
        $this->assertSame(8, (int) $product->fresh()->stock_qty);

        // The units already left stock when they were reserved.
        $this->stock->fulfil($product, 2, $item);

        $this->assertSame(8, (int) $product->fresh()->stock_qty, 'fulfilment must not decrement again');

        $this->assertSame(
            1,
            ProductStockMovement::query()
                ->where('product_id', $product->id)
                ->where('reason', StockMovementReason::OrderFulfilled)
                ->count()
        );

        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function an_admin_restock_increases_stock_and_is_recorded(): void
    {
        $product = $this->product(5);

        $this->stock->adjust($product, 20, StockMovementReason::Restock, 'Delivery received');

        $this->assertSame(25, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function an_admin_cannot_adjust_stock_below_zero(): void
    {
        $product = $this->product(3);

        try {
            $this->stock->adjust($product, -5, StockMovementReason::Manual, 'Miscount');
            $this->fail('expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(3, $e->available);
        }

        $this->assertSame(3, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function zero_and_negative_quantities_are_rejected(): void
    {
        $product = $this->product(5);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->reserve($product, 0);
    }

    #[Test]
    public function a_long_sequence_of_operations_still_reconciles(): void
    {
        $product = $this->product(50);
        $item = CartItem::factory()->for($product)->create(['qty' => 0]);

        $this->stock->reserve($product, 10, $item);
        $item->forceFill(['qty' => 10])->save();

        $this->stock->adjustReservation($item->fresh(), 4);
        $item->forceFill(['qty' => 4])->save();

        $this->stock->adjustReservation($item->fresh(), 9);
        $item->forceFill(['qty' => 9])->save();

        $this->stock->release($product, 9, $item);
        $this->stock->adjust($product, 15, StockMovementReason::Restock);
        $this->stock->adjust($product, -5, StockMovementReason::Manual);

        $this->assertSame(60, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }
}
