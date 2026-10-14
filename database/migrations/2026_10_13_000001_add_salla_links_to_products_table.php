<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link from each catalog product to its page on the Salla storefront.
 *
 * Written only by `php artisan kotiva:salla-links`, matched by SKU, and never
 * editable in the dashboard: a hand-typed product URL is how a "Buy" button
 * ends up on the wrong product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('salla_product_id')->nullable()->unique()->after('sku');
            $table->string('salla_url', 500)->nullable()->after('salla_product_id');
            $table->timestamp('salla_synced_at')->nullable()->after('salla_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['salla_product_id']);
            $table->dropColumn(['salla_product_id', 'salla_url', 'salla_synced_at']);
        });
    }
};
