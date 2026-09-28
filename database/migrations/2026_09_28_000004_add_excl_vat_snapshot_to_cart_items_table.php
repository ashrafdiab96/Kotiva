<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The cart's second price snapshot.
 *
 * A cart line already records the price it was added at, so checkout can tell
 * the shopper a figure has moved rather than quietly re-pricing them. With two
 * authoritative prices per product, one snapshot can no longer do that job: an
 * admin who corrects only the exclusive price moves the cart's headline figure
 * while the inclusive snapshot still matches, and the shopper would be shown a
 * different subtotal with no explanation.
 *
 * So the existing column keeps its meaning — the VAT-inclusive price at the
 * time of adding — and this adds its exclusive counterpart. The change
 * notification fires when EITHER has moved.
 *
 * Backfilled at the configured rate, which is correct for every cart in flight:
 * these rows live for the cart TTL (72 hours by default) and were written under
 * the rate that is configured now.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rate = self::rate();

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->decimal('unit_price_excl_vat_snapshot', 10, 2)
                ->nullable()
                ->after('unit_price_snapshot');
        });

        DB::table('cart_items')->update([
            'unit_price_excl_vat_snapshot' => DB::raw(
                'ROUND(`unit_price_snapshot` / '.(1 + $rate).', 2)'
            ),
        ]);

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->decimal('unit_price_excl_vat_snapshot', 10, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn('unit_price_excl_vat_snapshot');
        });
    }

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
