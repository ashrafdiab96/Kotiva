<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\StockLow;
use App\Listeners\SendLowStockAlert;
use App\Listeners\SendOrderPlacedEmails;
use App\Listeners\SendOrderStatusEmail;
use Illuminate\Support\Facades\Event;

/**
 * Runs a test against Laravel's retired commerce engine.
 *
 * Since the Salla handoff the cart, checkout, stock and order code is still
 * in the repository but switched off: kotiva.commerce_enabled is false and
 * the commerce listeners are no longer discovered. Tests that pin that code's
 * behaviour at the SERVICE or DASHBOARD level keep running by switching it
 * back on here — their assertions are unchanged. Tests that went through the
 * storefront's HTTP routes cannot, because those routes now redirect to Salla
 * or answer 410; they are skipped with a reason instead.
 *
 * Delete this trait together with the commerce code.
 */
trait EnablesLegacyCommerce
{
    protected function setUpEnablesLegacyCommerce(): void
    {
        config(['kotiva.commerce_enabled' => true]);

        Event::listen(OrderPlaced::class, SendOrderPlacedEmails::class);
        Event::listen(OrderStatusChanged::class, SendOrderStatusEmail::class);
        Event::listen(StockLow::class, SendLowStockAlert::class);
    }
}
