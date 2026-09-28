<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\StockLow;
use App\Mail\LowStockAlert;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the shop when a product crosses its low-stock threshold.
 *
 * Debounced to once per product per 24 hours (§6.8). StockService only fires
 * the event on the decrement that CROSSES the line, but a restock followed by
 * more sales can cross it again the same day — and an alert that arrives
 * repeatedly is an alert people learn to ignore.
 */
final class SendLowStockAlert
{
    public function handle(StockLow $event): void
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            Log::warning('Product hit its low-stock threshold but no alert recipient is configured', [
                'sku' => $event->product->sku,
                'remaining' => $event->remaining,
            ]);

            return;
        }

        $hours = (int) config('kotiva.stock.low_stock_alert_debounce_hours');
        $key = 'kotiva.low_stock_alert.'.$event->product->getKey();

        /*
         | Cache::add is atomic add-if-absent, so two concurrent sales that both
         | cross the threshold cannot both win the race and send two alerts.
         | A plain has()/put() pair could.
         */
        if (! Cache::add($key, true, now()->addHours($hours))) {
            return;
        }

        Log::info('Low stock alert sent', [
            'sku' => $event->product->sku,
            'remaining' => $event->remaining,
        ]);

        Mail::to($recipients)->send(new LowStockAlert($event->product, $event->remaining));
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $configured = Setting::get('low_stock_alert_email', config('kotiva.mail.store_email'));

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
