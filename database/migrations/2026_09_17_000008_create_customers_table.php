<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest checkout only in v1 — there is no customer login and no password
 * column. This table exists so the dashboard can recognise a repeat buyer by
 * email and show their history, which is the one thing guest checkout
 * otherwise throws away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            // Stored normalised to +9665XXXXXXXX so the same person entering
            // 05… one time and +9665… the next is still one customer.
            $table->string('phone');
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
