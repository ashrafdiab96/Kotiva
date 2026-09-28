<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // The only thing the browser holds, in an httpOnly cookie. A uuid
            // rather than the id so a guessed number cannot reach a stranger's
            // cart — and the cart itself never lives in localStorage.
            $table->uuid('token')->unique();

            $table->string('customer_email')->nullable();

            // expires_at = last_activity_at + settings.cart_ttl_hours. Both are
            // stored rather than derived so the purge command can index on
            // expiry without recomputing the TTL for every row.
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
