<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Nullable and nullOnDelete: a product may be removed from the
            // catalog years later, and that must not delete or corrupt the
            // order it was once sold on. The snapshots below are what keeps
            // the order readable after the product is gone.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sku_snapshot');
            $table->string('name_snapshot');
            $table->string('image_snapshot')->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('qty');
            $table->decimal('line_total', 10, 2);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
