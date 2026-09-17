<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether the money has arrived.
 *
 * Kept separate from OrderStatus on purpose: a cash-on-delivery order is
 * shipped while still unpaid, and collapsing the two would make "shipped" mean
 * "paid" — which for this store is exactly wrong.
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::Refunded => 'danger',
        };
    }
}
