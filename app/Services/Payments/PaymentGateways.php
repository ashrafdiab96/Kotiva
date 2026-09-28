<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use LogicException;

/**
 * Every registered payment gateway, looked up by the method the shopper chose.
 *
 * This is what makes PaymentGateway's promise true. The checkout used to be
 * constructed with CashOnDeliveryGateway directly, so "the checkout talks to
 * the interface, never a concrete class" was a comment, not a fact — a second
 * gateway would have needed controller surgery as well as its own class. Now
 * the checkout asks this registry, and a new gateway is registered once, in
 * AppServiceProvider.
 */
final class PaymentGateways
{
    /** @var array<string, PaymentGateway> */
    private array $byMethod = [];

    /**
     * @param  iterable<PaymentGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->byMethod[$gateway->method()] = $gateway;
        }
    }

    public function for(PaymentMethod $method): PaymentGateway
    {
        // A PaymentMethod case with no registered gateway is a wiring mistake,
        // not something a shopper did, so it is a loud failure rather than a
        // validation message.
        return $this->byMethod[$method->value]
            ?? throw new LogicException('No payment gateway is registered for "'.$method->value.'".');
    }

    public function isEnabled(PaymentMethod $method): bool
    {
        return isset($this->byMethod[$method->value]) && $this->byMethod[$method->value]->isEnabled();
    }
}
