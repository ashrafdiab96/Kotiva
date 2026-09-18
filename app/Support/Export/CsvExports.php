<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Models\Order;
use App\Models\Product;
use App\Support\Import\ProductImporter;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads (§7.2): the import template, products, and orders.
 *
 * The product export uses exactly the import's columns, so a file exported,
 * edited in Excel and imported again round-trips without a mapping step.
 *
 * Every cell goes through safe(). Order exports carry text customers typed —
 * a name of `=HYPERLINK(…)` would otherwise run as a formula on whichever
 * admin opened the file. The importer undoes the guard on the way back in.
 */
final class CsvExports
{
    public static function template(): StreamedResponse
    {
        return self::stream('kotiva-products-template.csv', function ($out): void {
            fputcsv($out, array_keys(ProductImporter::FIELDS));
            fputcsv($out, array_map(self::safe(...), [
                'KOT999', 'Kotiva Example Serum', '149.00', 'Serums', '', '', '50', '5',
                'All Skin Types', 'Hydration', 'Hydrates', '30ml',
                'One paragraph describing the product.',
                'Hydrates|Plumps|Smooths', 'Apply two drops morning and night.', '',
                'Hyaluronic Acid|Niacinamide', 'Parabens|Fragrance', 'face|serums',
                'assets/products/placeholder.webp', 'yes', 'no', 'no', '', '', '', '',
            ]));
        });
    }

    /**
     * @param  Builder<Product>|null  $query
     */
    public static function products(?Builder $query = null): StreamedResponse
    {
        $query ??= Product::query();

        return self::stream('kotiva-products-'.now()->format('Y-m-d').'.csv', function ($out) use ($query): void {
            fputcsv($out, array_keys(ProductImporter::FIELDS));

            $query->with('category')->orderBy('sort_order')->lazy(200)->each(function (Product $p) use ($out): void {
                fputcsv($out, array_map(self::safe(...), [
                    $p->sku, $p->name, $p->price, $p->category->name, $p->slug,
                    $p->compare_at_price, $p->stock_qty, $p->low_stock_threshold,
                    $p->skin_type, $p->concern, $p->action, $p->volume,
                    $p->description, implode('|', $p->benefits ?? []), $p->how_to_use, $p->science,
                    implode('|', $p->ingredients ?? []), implode('|', $p->free_from ?? []),
                    implode('|', $p->filter_tags ?? []), $p->image,
                    $p->is_active ? 'yes' : 'no', $p->is_featured ? 'yes' : 'no', $p->is_best_seller ? 'yes' : 'no',
                    $p->sort_order, $p->weight_grams, $p->meta_title, $p->meta_description,
                ]));
            });
        });
    }

    /**
     * @param  Builder<Order>|null  $query  e.g. the orders table's current filters
     */
    public static function orders(?Builder $query = null): StreamedResponse
    {
        $query ??= Order::query();

        return self::stream('kotiva-orders-'.now()->format('Y-m-d').'.csv', function ($out) use ($query): void {
            fputcsv($out, [
                'order_no', 'placed_at', 'status', 'payment_status', 'payment_method',
                'customer', 'email', 'phone', 'city', 'zone', 'units',
                'subtotal', 'shipping_fee', 'vat_amount', 'grand_total', 'currency',
            ]);

            $query->with(['customer', 'shippingZone'])->withSum('items', 'qty')
                ->orderByDesc('placed_at')->lazy(200)->each(function (Order $o) use ($out): void {
                    fputcsv($out, array_map(self::safe(...), [
                        $o->order_no, $o->placed_at?->format('Y-m-d H:i'), $o->status->label(),
                        $o->payment_status->label(), $o->payment_method->label(),
                        $o->customer?->fullName(), $o->customer?->email, $o->customer?->phone,
                        $o->shipping_city_name, $o->shippingZone?->name, (int) ($o->items_sum_qty ?? 0),
                        $o->subtotal, $o->shipping_fee, $o->vat_amount, $o->grand_total, $o->currency,
                    ]));
                });
        });
    }

    /**
     * Neutralises a cell a spreadsheet would execute as a formula.
     */
    public static function safe(mixed $value): string
    {
        $string = $value === null ? '' : (string) $value;

        return preg_match("/^[=+\\-@\t\r]/", $string) === 1 ? "'".$string : $string;
    }

    /**
     * @param  callable(resource): void  $write
     */
    private static function stream(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM, so Excel opens Arabic city names as UTF-8 rather than mojibake.
            fwrite($out, "\u{FEFF}");
            $write($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
