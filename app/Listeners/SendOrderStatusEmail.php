<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusUpdated;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the customer when their order ships, arrives or is cancelled.
 *
 * The decision about which statuses warrant a message lives on the event and
 * the enum, not here — so the dashboard, the tests and this listener cannot
 * disagree about what the customer hears.
 */
final class SendOrderStatusEmail
{
    public function handle(OrderStatusChanged $event): void
    {
        if (! $event->shouldNotifyCustomer()) {
            return;
        }

        Mail::to($event->order->customer->email)
            ->send(new OrderStatusUpdated($event->order, $event->to));
    }
}
