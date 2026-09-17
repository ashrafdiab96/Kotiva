<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order moved between statuses.
 *
 * Carries both ends of the move, not just the new value: a listener deciding
 * whether to email needs to know what changed, and "set to shipped" from an
 * order already shipped should not send a second notification.
 */
final class OrderStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}

    /**
     * Whether this particular move is one the customer should hear about.
     */
    public function shouldNotifyCustomer(): bool
    {
        return $this->from !== $this->to && $this->to->notifiesCustomer();
    }
}
