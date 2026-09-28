<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new order exists.
 *
 * Fired after the placement transaction commits, never inside it: a queued
 * listener that reads the order from another connection must be able to find
 * it, and mail must not be sent for an order that then rolls back.
 */
final class OrderPlaced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
