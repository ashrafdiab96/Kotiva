<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order is a RECORD, not a view over current data.
 *
 * Every figure and address field is stored on the row rather than joined from
 * products, customers or shipping rates. Changing a price, renaming a city or
 * editing a shipping rate tomorrow must not silently rewrite what someone was
 * charged and where it went — §7.3 states this explicitly for rates, and the
 * same reasoning applies to all of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // KOT-XXXXXX. Unique index is the real guarantee behind
            // App\Support\OrderNumber's retry loop.
            $table->string('order_no')->unique();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->enum('status', [
                'pending', 'confirmed', 'processing',
                'shipped', 'delivered', 'cancelled', 'refunded',
            ])->default('pending');

            $table->enum('payment_method', ['cod'])->default('cod');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');

            $table->string('currency', 3)->default('SAR');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            // Informational only: prices are VAT-inclusive, so this is the VAT
            // portion OF the total, never added to it.
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);

            // Kept as references for reporting, but the names below are what
            // the order actually shipped to.
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete();
            $table->foreignId('shipping_city_id')->nullable()->constrained('shipping_cities')->nullOnDelete();

            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_district')->nullable();
            $table->string('shipping_city_name');
            $table->string('shipping_postal_code')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index(['payment_status', 'placed_at']);
            $table->index('placed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
