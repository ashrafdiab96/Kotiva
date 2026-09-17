<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\GatedWidget;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Top 10 products by quantity sold (§7.1).
 *
 * Grouped by `sku_snapshot`, not `product_id`. The order_items FK is
 * nullable with nullOnDelete precisely so an order survives its product being
 * removed from the catalog — so grouping by the FK would silently erase the
 * entire sales history of every discontinued product, which is usually the one
 * you most want to see in a year-end top ten.
 *
 * Counts only revenue statuses, for the same reason the revenue figures do:
 * a cancelled order sold nothing.
 */
final class TopProductsChart extends ChartWidget
{
    use GatedWidget;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Top products by units sold';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    protected static function requiredCapability(): string
    {
        return 'canViewReports';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = self::topProducts();

        return [
            'datasets' => [
                [
                    'label' => 'Units sold',
                    'data' => array_map(fn (array $r): int => $r['qty'], $rows),
                    'backgroundColor' => '#005DBA',
                    'borderRadius' => 2,
                ],
            ],
            'labels' => array_map(fn (array $r): string => $r['name'], $rows),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }

    /**
     * @return list<array{sku: string, name: string, qty: int}>
     */
    public static function topProducts(int $limit = 10): array
    {
        // The query builder, not Eloquent: these rows are aggregates, and
        // hydrating them as OrderItem models would claim `sku` and `name` are
        // columns on that model when they are aliases that exist only here.
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', OrderStatus::revenueStatuses())
            // MAX() picks a representative label: a product renamed mid-life
            // has two snapshots under one SKU, and the SKU is the identity.
            ->selectRaw('order_items.sku_snapshot as sku, MAX(order_items.name_snapshot) as name, SUM(order_items.qty) as qty')
            ->groupBy('order_items.sku_snapshot')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();

        $top = [];

        foreach ($rows as $row) {
            $top[] = [
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'qty' => (int) $row->qty,
            ];
        }

        return $top;
    }
}
