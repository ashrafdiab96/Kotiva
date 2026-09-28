<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;

/**
 * The seam an online payment provider plugs into later.
 *
 * §6.6 requires that adding Tabby, Moyasar or similar is three changes: a case
 * on PaymentMethod, an implementation of this interface, and a callback route.
 * That only holds if nothing outside those three places asks "which gateway is
 * this?" — so the checkout talks to this interface and never to a concrete
 * class.
 *
 * No gateway integration is built here, deliberately. The interface exists
 * because designing it after the fact is what forces the payment branch to
 * leak through the checkout.
 */
interface PaymentGateway
{
    /**
     * The PaymentMethod case this gateway serves, as its backed value.
     */
    public function method(): string;

    /**
     * Whether this gateway may currently be offered.
     */
    public function isEnabled(): bool;

    /**
     * Start payment for an order.
     *
     * Returns the result the checkout should act on: a redirect-away gateway
     * returns a URL, an on-site one returns none and the order is complete.
     */
    public function initiate(Order $order): PaymentResult;

    /**
     * The payment status an order should carry the moment it is placed.
     *
     * For cash on delivery this is Unpaid — the money arrives with the
     * courier, and marking it paid at checkout would misreport revenue.
     */
    public function initialStatus(): PaymentStatus;
}
