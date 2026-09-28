<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT snapshots on orders.
 *
 * An order is a record, not a view over current data, so the dual-pricing
 * breakdown has to be stored the same way every other figure on the row
 * already is. Once written, none of these move again — a price change, a VAT
 * rate change or a shipping rule change must leave a placed order reading
 * exactly as it did on the day.
 *
 * The existing columns keep their meanings, which is what makes this additive:
 *   subtotal     — merchandise, VAT INCLUDED (unchanged)
 *   vat_amount   — all VAT contained in the order (unchanged)
 *   grand_total  — the payable figure (unchanged)
 *
 * New:
 *   subtotal_excl_vat    — merchandise with VAT removed
 *   product_vat_amount   — the VAT inside the merchandise
 *   shipping_vat_amount  — the VAT attributable to delivery
 *   vat_rate             — the rate this order was computed at
 *
 * Backfilling vat_rate is the interesting part. Rather than stamping today's
 * configured rate onto historical orders — which would misstate them the
 * moment the client ever changes it — the rate is RECOVERED from each order's
 * own figures: vat_amount was extracted as total × r / (1 + r), so
 * r = vat_amount / (total − vat_amount). Orders with no VAT recorded fall back
 * to the configured rate, since there is nothing in the row to recover from.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fallbackRate = self::fallbackRate();

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('subtotal_excl_vat', 10, 2)->nullable()->after('subtotal');
            $table->decimal('product_vat_amount', 10, 2)->nullable()->after('vat_amount');
            $table->decimal('shipping_vat_amount', 10, 2)->nullable()->after('product_vat_amount');
            // 6 decimals: the dashboard accepts a fractional percentage, and a
            // rate rounded to 4 would not reproduce the order's own arithmetic.
            $table->decimal('vat_rate', 8, 6)->nullable()->after('shipping_vat_amount');
        });

        /*
         | Recover the rate each order actually used.
         |
         | NULLIF guards the zero denominator: an order whose VAT equals its
         | total would be a division by zero, and a NULL there is then filled
         | with the configured rate by the statement after this one.
         */
        DB::table('orders')->update([
            'vat_rate' => DB::raw(
                'ROUND(`vat_amount` / NULLIF(`grand_total` - `vat_amount`, 0), 6)'
            ),
        ]);

        DB::table('orders')
            ->where(fn ($q) => $q->whereNull('vat_rate')->orWhere('vat_rate', '<=', 0))
            ->update(['vat_rate' => $fallbackRate]);

        /*
         | Snap rounding noise back onto the configured rate.
         |
         | vat_amount is stored to 2dp, so recovering the rate from it returns
         | something like 0.149994 rather than 0.15 — arithmetically the rate
         | that order's rounded figures imply, but it would print as
         | "14.9994% VAT" on an admin screen for an order charged at 15%.
         |
         | The noise is bounded by half a halala spread over the order's net
         | total, which is at most a few parts in 100,000; a real rate change is
         | at least half a percent. An epsilon of 0.001 therefore sits two
         | orders of magnitude clear of both, so this cannot mask an order that
         | genuinely used a different rate.
         */
        DB::table('orders')
            ->whereRaw('ABS(`vat_rate` - ?) < 0.001', [$fallbackRate])
            ->update(['vat_rate' => $fallbackRate]);

        /*
         | Split the historical vat_amount between merchandise and delivery
         | using the order's own recovered rate.
         |
         | The existing rule — which this preserves — is that the delivery fee
         | was VAT-inclusive and vat_amount was extracted from the grand total,
         | so the delivery share is fee × r / (1 + r) and the rest is product
         | VAT. Deriving product VAT as the remainder rather than independently
         | guarantees the two still add back up to the stored vat_amount.
         */
        DB::table('orders')->update([
            'shipping_vat_amount' => DB::raw(
                'ROUND(`shipping_fee` * `vat_rate` / (1 + `vat_rate`), 2)'
            ),
        ]);

        DB::table('orders')->update([
            'product_vat_amount' => DB::raw('ROUND(`vat_amount` - `shipping_vat_amount`, 2)'),
        ]);

        DB::table('orders')->update([
            'subtotal_excl_vat' => DB::raw('ROUND(`subtotal` - `product_vat_amount`, 2)'),
        ]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('subtotal_excl_vat', 10, 2)->nullable(false)->change();
            $table->decimal('product_vat_amount', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('shipping_vat_amount', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('vat_rate', 8, 6)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'subtotal_excl_vat',
                'product_vat_amount',
                'shipping_vat_amount',
                'vat_rate',
            ]);
        });
    }

    private static function fallbackRate(): float
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
