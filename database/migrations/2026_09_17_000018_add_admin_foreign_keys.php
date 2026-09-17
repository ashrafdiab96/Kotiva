<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two foreign keys deferred from phases 2 and 4.
 *
 * `product_stock_movements.admin_id` and `order_status_histories.admin_id` were
 * created as plain nullable columns because the admins table did not exist yet,
 * with a comment in each migration promising the constraint would arrive with
 * the dashboard. This is that migration — an unconstrained id column is exactly
 * how an audit trail ends up pointing at an admin who never existed.
 *
 * nullOnDelete rather than cascade: deleting an admin must never delete the
 * stock movements or status changes they made. The history outlives the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });
    }
};
