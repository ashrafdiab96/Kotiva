<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            $table->decimal('fee', 10, 2);
            // Null means this zone never ships free.
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();

            $table->unsignedSmallInteger('estimated_days_min');
            $table->unsignedSmallInteger('estimated_days_max');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
