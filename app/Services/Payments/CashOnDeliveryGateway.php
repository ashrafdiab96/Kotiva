<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Setting;

/**
 * The only payment method in v1.
 *
 * There is nothing to charge at checkout: the courier collects. So initiating
 * payment completes immediately and the order is created Unpaid, to be marked
 * Paid from the dashboard once the money is actually in hand.
 */
final class CashOnDeliveryGateway implements PaymentGateway
{
    public function method(): string
    {
        return PaymentMethod::CashOnDelivery->value;
    }

    /**
     * Toggleable from the dashboard. If an admin turns COD off while it is the
     * only method, checkout has nothing to offer — CheckoutService is what
     * reports that, rather than this returning a misleading true.
     */
    public function isEnabled(): bool
    {
        return (bool) Setting::get('cod_enabled', true);
    }

    public function initiate(Order $order): PaymentResult
    {
        return PaymentResult::completed(reference: $order->order_no);
    }

    public function initialStatus(): PaymentStatus
    {
        return PaymentStatus::Unpaid;
    }
}
