<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();              // KOT001 …
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('skin_type')->nullable();
            $table->string('concern')->nullable();
            $table->string('action')->nullable();
            $table->string('volume')->nullable();

            // VAT-inclusive, SAR. Money is never a float.
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();

            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->text('how_to_use')->nullable();
            $table->longText('science')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('free_from')->nullable();
            $table->json('filter_tags')->nullable();

            $table->string('image')->nullable();
            $table->json('gallery')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_active')->default(true);

            // Cached current stock. The authoritative history is
            // product_stock_movements; this column is what gets row-locked.
            $table->unsignedInteger('stock_qty')->default(0);
            $table->integer('low_stock_threshold')->default(5);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_featured']);
            $table->index(['is_active', 'is_best_seller']);
            $table->index(['category_id', 'is_active']);
            $table->index('stock_qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
