<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an order is paid.
 *
 * Cash on delivery is the only method for now. §6.6 requires adding an online
 * gateway later to be a three-step change: a case here, a PaymentGateway
 * implementation, and a callback route — so nothing outside those three places
 * may branch on this value.
 */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on Delivery',
        };
    }

    /**
     * Short form for the customer-facing summary and emails.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'COD',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'warning',
        };
    }

    /**
     * Whether the shopper is sent off-site to pay before the order is placed.
     * COD is not, so the order is created directly on the payment step.
     */
    public function requiresRedirect(): bool
    {
        return match ($this) {
            self::CashOnDelivery => false,
        };
    }
}
