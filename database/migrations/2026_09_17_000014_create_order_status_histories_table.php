<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit of every status change, so "who cancelled this and when"
 * always has an answer. Each row is also what the order timeline renders from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Null `from` marks the order's creation.
            $table->string('from_status')->nullable();
            $table->string('to_status');

            // No FK yet — the admins table arrives with the dashboard in
            // phase 5, which adds the constraint rather than dangling it here.
            $table->unsignedBigInteger('admin_id')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
