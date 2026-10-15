<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per /dr/<code> hit. Visits only: orders and commission are counted
 * by Salla's affiliate dashboard, which is the source of truth (D-38).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_partner_id')->constrained()->cascadeOnDelete();
            $table->timestamp('visited_at');
            // sha256(ip . APP_KEY) — never the raw address.
            $table->char('ip_hash', 64);
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->string('landing_path');
            // First visit from this ip_hash for this partner on this calendar day.
            $table->boolean('is_unique_day')->default(true);
            $table->timestamps();

            $table->index(['referral_partner_id', 'visited_at']);
            $table->index(['referral_partner_id', 'ip_hash', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_visits');
    }
};
