<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name_en');
            // Arabic name carried from the start: the storefront is English
            // today, but a KSA store will want it and back-filling city names
            // later is far worse than storing them now.
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
            $table->unique(['zone_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_cities');
    }
};
