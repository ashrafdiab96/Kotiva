<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Checkout could not proceed for a reason the shopper needs to act on.
 *
 * Each named constructor carries a message written for the customer, not for a
 * log: by the payment step they have entered an address and committed to
 * buying, so "something went wrong" is the one thing that must never appear.
 */
final class CheckoutException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    public static function productUnavailable(string $productName): self
    {
        return new self(sprintf(
            '%s is no longer available and has been removed from your cart.',
            $productName
        ));
    }

    public static function paymentUnavailable(): self
    {
        return new self('No payment method is currently available. Please contact us to complete your order.');
    }

    /**
     * The order was already placed by an earlier submission of the same form.
     */
    public static function alreadyPlaced(): self
    {
        return new self('This order has already been placed.');
    }
}
