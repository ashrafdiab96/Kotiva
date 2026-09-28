<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard users.
 *
 * Deliberately a separate table from `users`, not a role column on it. The
 * storefront has no customer login at all in v1, so `users` has no legitimate
 * occupant — sharing a table would mean the same credentials surface could one
 * day serve both shoppers and staff, which is how a customer account ends up
 * one flag away from the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            $table->string('role')->default('staff');

            // Revoking access without deleting the account, so their history
            // in order_status_histories keeps its author.
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
