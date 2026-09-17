<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the runtime settings with the same values config/kotiva.php falls back
 * to, so the dashboard shows real values on a fresh install rather than empty
 * fields that look unconfigured.
 */
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'store_email' => config('kotiva.mail.store_email'),
            'admin_notification_emails' => config('kotiva.mail.admin_notification_emails'),
            'low_stock_alert_email' => config('kotiva.mail.store_email'),
            'cod_enabled' => true,
            'cart_ttl_hours' => (int) config('kotiva.cart.ttl_hours'),
            'low_stock_threshold' => (int) config('kotiva.stock.low_stock_threshold'),
            'vat_rate' => (float) config('kotiva.vat_rate'),
            'announcement_bar' => [
                'enabled' => false,
                'text' => '',
            ],
        ];

        foreach ($defaults as $key => $value) {
            // firstOrCreate, not updateOrCreate: re-seeding must never stamp
            // over a value the client has changed from the dashboard.
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flush();
    }
}
