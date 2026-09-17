<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('qty');

            // The price when the line was added. Checkout deliberately re-prices
            // from the product and tells the shopper if this figure has moved,
            // so the snapshot is evidence of a change, never the amount charged.
            $table->decimal('unit_price_snapshot', 10, 2);

            $table->timestamps();

            // One line per product: adding the same product again adjusts the
            // existing line's quantity rather than creating a second line, which
            // is also what keeps the stock reservation per line unambiguous.
            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
