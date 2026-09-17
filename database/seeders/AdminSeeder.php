<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds the first super-admin from .env (§4).
 *
 * firstOrCreate, never updateOrCreate: re-running the seeder must not reset a
 * password the client has since changed — and `migrate:fresh --seed` is run
 * often enough in development that quietly restoring the .env password would
 * be a genuine lock-out risk in any shared environment.
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Via config, not env(): env() returns null once config:cache has run,
        // which would silently seed no admin on a production install.
        $email = (string) config('kotiva.admin.email', '');
        $password = (string) config('kotiva.admin.password', '');

        if ($email === '' || $password === '') {
            // Said out loud rather than skipped silently: an install with no
            // admin account has no way into the dashboard at all.
            Log::warning('AdminSeeder skipped — KOTIVA_ADMIN_EMAIL / KOTIVA_ADMIN_PASSWORD are not set');

            return;
        }

        Admin::firstOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => (string) config('kotiva.admin.name', 'KOTIVA Admin'),
                // Hashed by the model's 'hashed' cast.
                'password' => $password,
                'role' => AdminRole::SuperAdmin,
                'is_active' => true,
            ]
        );
    }
}
