<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate:fresh --seed` must produce a fully working store.
 *
 * Order matters: settings first so anything reading a setting during seeding
 * gets a real value rather than a config fallback, and the admin account last
 * so a failure there cannot leave the catalog half-seeded.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            ShippingSeeder::class,
            ProductSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
