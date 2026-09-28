<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderPlacedAdmin;
use App\Mail\OrderPlacedCustomer;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Confirms the order to the customer and tells the shop about it.
 *
 * Not queued itself — the Mailables are. Queuing the listener as well would
 * add a second hop for no benefit, and would mean a failed queue job loses the
 * decision about *who* to mail rather than just one message.
 */
final class SendOrderPlacedEmails
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        Mail::to($order->customer->email)->send(new OrderPlacedCustomer($order));

        $recipients = $this->adminRecipients();

        if ($recipients === []) {
            // Worth saying out loud: the shop would otherwise never learn an
            // order had arrived, and silence looks identical to no orders.
            Log::warning('Order placed but no admin notification recipients are configured', [
                'order_no' => $order->order_no,
            ]);

            return;
        }

        Mail::to($recipients)->send(new OrderPlacedAdmin($order));
    }

    /**
     * Dashboard setting first, config fallback — so a fresh install still
     * notifies someone before an admin has touched the settings page.
     *
     * @return list<string>
     */
    private function adminRecipients(): array
    {
        $configured = Setting::get('admin_notification_emails', config('kotiva.mail.admin_notification_emails'));

        $list = is_array($configured)
            ? $configured
            : array_map('trim', explode(',', (string) $configured));

        return array_values(array_filter(
            $list,
            static fn (mixed $email): bool => is_string($email)
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
