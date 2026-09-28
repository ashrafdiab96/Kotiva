<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dual pricing: every product carries both brochure prices.
 *
 * The client's two A2 brochures are both authoritative — one prints
 * VAT-exclusive prices, the other VAT-inclusive — so the catalog stores both
 * rather than deriving one from the other at render time. The storefront leads
 * with the exclusive price and checkout charges the inclusive one, and neither
 * figure is a calculation anybody can silently disagree with.
 *
 * `price` is KEPT and becomes a maintained mirror of `price_incl_vat`. It is
 * not dropped, for three reasons: it is the column an existing production dump
 * carries, it is what the retired `legacy/js/data-lite.js` bundle and any
 * outside report still read, and dropping a money column from a live catalog is
 * not something a column rename is worth. App\Models\Product keeps the two in
 * step on save; nothing in the application reads `price` any more.
 *
 * Backfill is deliberately two-sided:
 *   - price_incl_vat takes the existing `price` verbatim. That value IS the
 *     inclusive brochure price for all 25 launch SKUs (verified against
 *     docs/pricing/brochure-prices.json), so nothing is recalculated.
 *   - price_excl_vat is derived once, here, at the configured rate, because no
 *     exclusive figure exists in the database yet to preserve. The brochure
 *     seeder then writes the authoritative exclusive prices over it for the
 *     SKUs the brochures cover.
 *
 * Existing orders are untouched: they carry their own snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rate = self::rate();

        Schema::table('products', function (Blueprint $table): void {
            // Nullable first, so the backfill below has somewhere to write on a
            // table that already has rows. Made non-nullable at the end.
            $table->decimal('price_excl_vat', 10, 2)->nullable()->after('price');
            $table->decimal('price_incl_vat', 10, 2)->nullable()->after('price_excl_vat');
        });

        // The inclusive price is the existing column, unchanged and unrounded.
        DB::table('products')->update([
            'price_incl_vat' => DB::raw('`price`'),
        ]);

        /*
         | The exclusive price is incl ÷ (1 + r), rounded half-up to 2dp in SQL.
         |
         | ROUND() rather than a cast: truncating here would understate every
         | exclusive price by up to a halala and the two columns would no longer
         | reconcile at the configured rate, which is the exact condition the
         | admin form is built to flag.
         */
        DB::table('products')->update([
            'price_excl_vat' => DB::raw('ROUND(`price` / '.(1 + $rate).', 2)'),
        ]);

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('price_excl_vat', 10, 2)->nullable(false)->change();
            $table->decimal('price_incl_vat', 10, 2)->nullable(false)->change();
        });

        // Price sorting on the shop moves to the exclusive price, which is what
        // the listing now displays.
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['is_active', 'price_excl_vat'], 'products_active_price_excl_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_active_price_excl_index');
            $table->dropColumn(['price_excl_vat', 'price_incl_vat']);
        });
    }

    /**
     * The rate to derive exclusive prices at.
     *
     * Read from the settings row when one exists, exactly as the application
     * would, so a client who has already changed their VAT rate is backfilled
     * at their rate and not at the config default. Read with the query builder
     * rather than the model, because a migration must not depend on the state
     * of the application's cache.
     */
    private static function rate(): float
    {
        $stored = Schema::hasTable('settings')
            ? DB::table('settings')->where('key', 'vat_rate')->value('value')
            : null;

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);

            if (is_numeric($decoded) && (float) $decoded > 0) {
                return (float) $decoded;
            }
        }

        $configured = (float) config('kotiva.vat_rate');

        return $configured > 0 ? $configured : 0.15;
    }
};
