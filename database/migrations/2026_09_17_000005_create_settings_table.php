<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime settings the client changes from the dashboard.
 *
 * The split against config/kotiva.php is deliberate: anything here is expected
 * to change without a deploy (COD on/off, cart TTL, announcement bar), while
 * config holds deploy-time constants (currency, order-number alphabet). Config
 * values act as the fallback when a row has not been written yet, so the store
 * works correctly on a fresh database before an admin has touched anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // JSON so a setting can hold a list (admin notification emails) or a
            // structure (announcement bar text + enabled) without a schema change.
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
