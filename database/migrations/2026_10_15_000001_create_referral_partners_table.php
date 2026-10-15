<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctors (and anyone else) who share a /dr/<code> link. Salla owns the
 * affiliate itself; this row only maps our short code onto the link Salla
 * generated for them. See docs/DECISIONS.md D-38.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The /dr/<code> slug. Unique across soft-deleted rows too, so a
            // retired doctor's printed link can never start crediting someone else.
            $table->string('code', 32)->unique();
            // The full link copied from Salla's Affiliate marketing screen; only
            // its query string is used (App\Support\Referral::affiliateQuery()).
            $table->string('salla_affiliate_url', 2048);
            $table->string('landing_path')->default('/');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_partners');
    }
};
