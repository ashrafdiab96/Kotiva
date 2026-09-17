<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Events\StockLow;
use App\Exceptions\InsufficientStockException;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The single place products.stock_qty is allowed to change.
 *
 * Two invariants hold for every method here:
 *
 *   1. stock_qty is NEVER negative. Every decrement re-reads the row under
 *      `SELECT … FOR UPDATE` inside a transaction and re-checks availability
 *      after acquiring the lock — checking before the lock is the classic
 *      oversell race, where two requests both read 1 remaining and both
 *      succeed.
 *
 *   2. Every change writes a product_stock_movements row. stock_qty is a cached
 *      figure; the ledger is the truth, and the two must always reconcile
 *      (SUM(delta) per product == stock_qty).
 *
 * Adding to a cart RESERVES stock rather than merely counting it, so the last
 * unit cannot be promised to two shoppers who both reach checkout.
 */
final class StockService
{
    /**
     * Hold `$qty` units for a cart line.
     *
     * @throws InsufficientStockException
     */
    public function reserve(Product $product, int $qty, ?CartItem $reference = null): void
    {
        $this->assertPositive($qty);

        $this->applyDecrement(
            $product,
            $qty,
            StockMovementReason::OrderReserved,
            $reference,
            'Reserved for cart'
        );
    }

    /**
     * Give `$qty` units back — item removed, cart emptied, cart expired, or an
     * order cancelled.
     */
    public function release(Product $product, int $qty, ?Model $reference = null, ?string $note = null): void
    {
        $this->assertPositive($qty);

        DB::transaction(function () use ($product, $qty, $reference, $note): void {
            $locked = $this->lock($product);

            $locked->forceFill(['stock_qty' => $locked->stock_qty + $qty])->save();

            $this->record($locked, $qty, StockMovementReason::OrderReleased, $reference, $note ?? 'Released back to stock');

            $product->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Move a cart line's reservation to a new quantity.
     *
     * Only the DELTA touches stock: going 2 → 3 reserves one more unit, 3 → 1
     * releases two. Releasing everything and re-reserving would briefly expose
     * the shopper's own held stock to another buyer.
     *
     * @throws InsufficientStockException
     */
    public function adjustReservation(CartItem $item, int $newQty): void
    {
        if ($newQty < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        $delta = $newQty - $item->qty;

        if ($delta === 0) {
            return;
        }

        $product = $item->product;

        if ($delta > 0) {
            $this->reserve($product, $delta, $item);

            return;
        }

        $this->release($product, -$delta, $item, 'Cart quantity reduced');
    }

    /**
     * Convert a cart line's reservation into a fulfilment against an order.
     *
     * The units are already out of stock_qty from the reservation, so this must
     * NOT decrement again: it closes the reservation with a zero-sum pair so the
     * ledger shows why the stock left, and stock_qty is untouched.
     */
    public function fulfil(Product $product, int $qty, Model $order): void
    {
        $this->assertPositive($qty);

        DB::transaction(function () use ($product, $qty, $order): void {
            $locked = $this->lock($product);

            // Release the reservation and immediately fulfil the same amount.
            // Net zero against stock_qty, but the ledger now attributes the
            // units to an order rather than to an open cart.
            $this->record($locked, $qty, StockMovementReason::OrderReleased, $order, 'Reservation closed by order');
            $this->record($locked, -$qty, StockMovementReason::OrderFulfilled, $order, 'Fulfilled by order');
        });
    }

    /**
     * Admin stock correction or restock.
     *
     * @throws InsufficientStockException when a negative adjustment would go below zero
     */
    public function adjust(Product $product, int $delta, StockMovementReason $reason, ?string $note = null, ?int $adminId = null): void
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('A stock adjustment must be non-zero.');
        }

        if ($delta > 0) {
            DB::transaction(function () use ($product, $delta, $reason, $note, $adminId): void {
                $locked = $this->lock($product);
                $locked->forceFill(['stock_qty' => $locked->stock_qty + $delta])->save();
                $this->record($locked, $delta, $reason, null, $note, $adminId);
                $product->setRawAttributes($locked->getAttributes(), true);
            });

            return;
        }

        $this->applyDecrement($product, -$delta, $reason, null, $note, $adminId);
    }

    /**
     * Shared decrement path: lock, re-check, decrement, record.
     *
     * @throws InsufficientStockException
     */
    private function applyDecrement(
        Product $product,
        int $qty,
        StockMovementReason $reason,
        ?Model $reference = null,
        ?string $note = null,
        ?int $adminId = null,
    ): void {
        $crossedThreshold = DB::transaction(function () use ($product, $qty, $reason, $reference, $note, $adminId): bool {
            $locked = $this->lock($product);

            // Re-checked AFTER the lock. The value read before locking is
            // already stale by the time we could act on it.
            if ($locked->stock_qty < $qty) {
                Log::info('Stock reservation refused', [
                    'sku' => $locked->sku,
                    'requested' => $qty,
                    'available' => $locked->stock_qty,
                    'reason' => $reason->value,
                ]);

                throw new InsufficientStockException($locked, $qty, $locked->stock_qty);
            }

            $wasAboveThreshold = $locked->stock_qty > $locked->low_stock_threshold;

            $locked->forceFill(['stock_qty' => $locked->stock_qty - $qty])->save();

            $this->record($locked, -$qty, $reason, $reference, $note, $adminId);

            // Keep the caller's instance consistent with what was committed.
            $product->setRawAttributes($locked->getAttributes(), true);

            // Only the decrement that CROSSES the line is interesting. Without
            // the "was above" test every subsequent sale of an already-low
            // product would raise another alert.
            return $wasAboveThreshold && $locked->stock_qty <= $locked->low_stock_threshold;
        });

        // Dispatched after commit: a queued listener on another connection
        // must be able to read the quantity that triggered it.
        if ($crossedThreshold) {
            StockLow::dispatch($product, (int) $product->stock_qty);
        }
    }

    /**
     * Re-read the row with a write lock held for the rest of the transaction.
     */
    private function lock(Product $product): Product
    {
        /** @var Product $locked */
        $locked = Product::query()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    private function record(
        Product $product,
        int $delta,
        StockMovementReason $reason,
        ?Model $reference = null,
        ?string $note = null,
        ?int $adminId = null,
    ): void {
        ProductStockMovement::create([
            'product_id' => $product->getKey(),
            'delta' => $delta,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'admin_id' => $adminId,
        ]);
    }

    private function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
    }
}
