<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §6.1 defines the default listing sort as "is_featured desc, sort_order", but
 * the products table as specified has no such column — ordering fell back to
 * insertion id, which is not something an admin can change.
 *
 * It also solves a real fidelity problem. The home page's bestseller rail was
 * hand-ordered in index.html (micellar → toner → UV balance → …), an editorial
 * sequence that exists in no data source. Reading bestsellers from the database
 * by id would silently reorder that rail. Seeding sort_order from the rail's
 * own order preserves the page exactly and makes the order editable later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('category_id');
            $table->index(['is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
