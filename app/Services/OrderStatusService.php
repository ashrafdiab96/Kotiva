<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementReason;
use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way an order's status may change.
 *
 * Centralised because a status change is never just a column write: it can
 * return stock to the catalog, it stamps a timestamp, it appends to the audit
 * trail and it decides whether the customer hears about it. Doing that in a
 * controller would mean doing it slightly differently in the next controller.
 */
final class OrderStatusService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Move an order to a new status.
     *
     * @throws InvalidOrderTransition
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?string $note = null,
        ?int $adminId = null,
    ): Order {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidOrderTransition($from, $to);
        }

        return DB::transaction(function () use ($order, $from, $to, $note, $adminId): Order {
            // Cancelling is what gives the units back. Done before the status
            // write so a failure here leaves the order in its previous state
            // rather than cancelled-but-still-holding-stock.
            if ($to->releasesStock()) {
                $this->restoreStock($order);
            }

            $attributes = ['status' => $to];

            if (($column = $to->timestampColumn()) !== null) {
                $attributes[$column] = now();
            }

            $order->forceFill($attributes)->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'admin_id' => $adminId,
                'note' => $note,
            ]);

            Log::info('Order status changed', [
                'order_no' => $order->order_no,
                'from' => $from->value,
                'to' => $to->value,
                'admin_id' => $adminId,
            ]);

            // Listeners decide whether the customer is emailed; the enum
            // decides which statuses warrant it.
            OrderStatusChanged::dispatch($order->fresh(), $from, $to);

            return $order->fresh();
        });
    }

    /**
     * Cash collected. Separate from the order status on purpose: a COD order is
     * shipped while still unpaid, so "delivered" must not imply "paid".
     */
    public function markPaid(Order $order, ?int $adminId = null, ?string $note = null): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'admin_id' => $adminId,
            'note' => $note ?? 'Marked as paid',
        ]);

        Log::info('Order marked paid', [
            'order_no' => $order->order_no,
            'admin_id' => $adminId,
        ]);

        return $order->fresh();
    }

    /**
     * Return a cancelled order's units to the catalog.
     *
     * Each line is released against the order, so the ledger reads as a
     * complete story: reserved for a cart, fulfilled by an order, released
     * when that order was cancelled.
     */
    private function restoreStock(Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            /** @var OrderItem $item */
            $product = $item->product;

            // The product may have been deleted from the catalog since. There
            // is nothing to restore stock to, and that is not an error.
            if (! $product instanceof Product) {
                continue;
            }

            $this->stock->release(
                $product,
                $item->qty,
                $order,
                'Order '.$order->order_no.' cancelled'
            );
        }
    }

    /**
     * Statuses an order may legally move to right now, for the dashboard's
     * action list. Returning the real set stops the UI offering a transition
     * the service will then refuse.
     *
     * @return list<OrderStatus>
     */
    public function availableTransitions(Order $order): array
    {
        return $order->status->allowedTransitions();
    }

    /**
     * Whether cancelling this order would put units back.
     */
    public function wouldRestoreUnits(Order $order): int
    {
        if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
            return 0;
        }

        return (int) $order->items()->sum('qty');
    }

    /**
     * Reason used when stock returns from a cancellation, exposed so the
     * dashboard can label the movement consistently.
     */
    public function cancellationReason(): StockMovementReason
    {
        return StockMovementReason::OrderReleased;
    }
}
