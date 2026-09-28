<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The order state machine.
 *
 * Transitions are enumerated rather than left to the dashboard's UI, because
 * each one has side effects — cancelling restores stock and emails the
 * customer, shipping emails the customer — and an unreachable or accidental
 * transition would fire those side effects wrongly.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * §7.4's allowed moves: pending→confirmed→processing→shipped→delivered,
     * and pending/confirmed→cancelled.
     *
     * delivered→refunded is added deliberately. The brief lists `refunded` in
     * the status enum but in none of its transitions, which would leave it a
     * state no order could ever reach. A refund follows a delivery, so that is
     * where it is allowed from. Nothing leaves cancelled or refunded: both are
     * terminal, and an order that needs to restart is a new order.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * True once the order is finished, in either direction.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Cancelling is what returns reserved stock to the catalog.
     */
    public function releasesStock(): bool
    {
        return $this === self::Cancelled;
    }

    /**
     * Statuses that count toward revenue reporting (§7.1).
     */
    public function countsAsRevenue(): bool
    {
        return match ($this) {
            self::Confirmed, self::Processing, self::Shipped, self::Delivered => true,
            self::Pending, self::Cancelled, self::Refunded => false,
        };
    }

    /**
     * The same rule as countsAsRevenue(), as query-ready values.
     *
     * Exists so reporting queries never restate the list of revenue statuses
     * inline: a second copy would keep working while quietly disagreeing with
     * this one the moment a status is added.
     *
     * @return list<string>
     */
    public static function revenueStatuses(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->countsAsRevenue())
        ));
    }

    /**
     * Whether a move to this status should email the customer (§6.8).
     */
    public function notifiesCustomer(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Filament badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'info',
            self::Processing => 'warning',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::Refunded => 'danger',
        };
    }

    /**
     * The timestamp column stamped when an order reaches this status, if any.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Confirmed => 'confirmed_at',
            self::Shipped => 'shipped_at',
            self::Delivered => 'delivered_at',
            self::Cancelled => 'cancelled_at',
            default => null,
        };
    }
}
