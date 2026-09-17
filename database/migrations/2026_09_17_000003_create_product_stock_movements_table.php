<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Signed: negative reserves/fulfils, positive releases/restocks.
            // products.stock_qty must always equal the sum of these deltas.
            $table->integer('delta');

            $table->enum('reason', [
                'manual',
                'import',
                'order_reserved',
                'order_released',
                'order_fulfilled',
                'restock',
            ]);

            // Points at the cart_item or order that caused the movement.
            $table->nullableMorphs('reference');

            $table->string('note')->nullable();

            // No FK yet — the admins table arrives with the dashboard in phase 5,
            // which adds the constraint rather than leaving it dangling here.
            $table->unsignedBigInteger('admin_id')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
    }
};
