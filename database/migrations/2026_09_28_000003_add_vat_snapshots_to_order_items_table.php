<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT snapshots on order lines.
 *
 * The existing columns keep their meanings — `unit_price` and `line_total` are
 * the VAT-INCLUSIVE figures the customer was charged — so every confirmation
 * email, packing slip and admin view already sent out still reads correctly.
 * What is added is the same line stated net, plus the VAT it contains and the
 * rate that VAT was computed at.
 *
 * Why store per-line VAT at all, when the order carries a total? Because a
 * packing slip, a credit note and a partial refund all work a line at a time,
 * and re-deriving a line's VAT years later would use today's rate on an order
 * priced at last year's.
 *
 * The backfill runs in PHP, chunked, rather than as one joined UPDATE. Cross-
 * database UPDATE ... JOIN syntax differs between MySQL and SQLite, and this
 * migration has to produce identical figures on the production database and in
 * the test suite. Each line is derived from its OWN order's recovered rate,
 * never from the configured one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_price_excl_vat', 10, 2)->nullable()->after('unit_price');
            $table->decimal('line_total_excl_vat', 10, 2)->nullable()->after('line_total');
            $table->decimal('vat_amount', 10, 2)->nullable()->after('line_total_excl_vat');
            $table->decimal('vat_rate', 8, 6)->nullable()->after('vat_amount');
        });

        self::backfill();

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_price_excl_vat', 10, 2)->nullable(false)->change();
            $table->decimal('line_total_excl_vat', 10, 2)->nullable(false)->change();
            $table->decimal('vat_amount', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('vat_rate', 8, 6)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'unit_price_excl_vat',
                'line_total_excl_vat',
                'vat_amount',
                'vat_rate',
            ]);
        });
    }

    private static function backfill(): void
    {
        DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->orderBy('order_items.id')
            ->select([
                'order_items.id',
                'order_items.unit_price',
                'order_items.line_total',
                'orders.vat_rate',
            ])
            ->chunk(500, function ($lines): void {
                foreach ($lines as $line) {
                    $rate = (float) $line->vat_rate;
                    $divisor = 1 + $rate;

                    $unitExcl = round(((float) $line->unit_price) / $divisor, 2);
                    $lineExcl = round(((float) $line->line_total) / $divisor, 2);

                    DB::table('order_items')->where('id', $line->id)->update([
                        'unit_price_excl_vat' => number_format($unitExcl, 2, '.', ''),
                        'line_total_excl_vat' => number_format($lineExcl, 2, '.', ''),
                        // The remainder, so the net figure and the VAT still add
                        // back up to the inclusive figure the customer paid.
                        'vat_amount' => number_format(((float) $line->line_total) - $lineExcl, 2, '.', ''),
                        'vat_rate' => number_format($rate, 6, '.', ''),
                    ]);
                }
            });
    }
};
