<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\GatedWidget;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Revenue and order count over time (§7.1), with the brief's date-range filter.
 *
 * Extends ChartWidget directly and declares getType() — LineChartWidget is
 * deprecated in this version of Filament.
 *
 * Two datasets on two axes: revenue in SAR and a plain order count. Plotting
 * both against one axis would flatten the count into the baseline, since a
 * day's revenue is two or three orders of magnitude larger than its orders.
 */
final class RevenueChart extends ChartWidget
{
    use GatedWidget;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Revenue and orders';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected static function requiredCapability(): string
    {
        return 'canViewReports';
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * The date-range filter §7.1 asks for.
     *
     * Keyed by day count. PHP casts numeric-string array keys to int, so the
     * real type is array<int, string> however it is written here — and the
     * selected key arrives back in $this->filter as a string, which is why
     * getData() coerces it rather than comparing loosely.
     *
     * Narrowed to `array` from the parent's `?array`, which PHP permits for a
     * return type: this implementation always offers the three ranges, so
     * declaring a null it can never return would be a lie to every caller.
     *
     * @return array<int, string>
     */
    protected function getFilters(): array
    {
        return [
            7 => 'Last 7 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $days = max(1, (int) ($this->filter ?? '30'));
        $series = self::series($days);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ('.config('kotiva.currency.code').')',
                    'data' => array_map(fn (array $row): float => $row['revenue'], $series),
                    'borderColor' => '#005DBA',
                    'backgroundColor' => 'rgba(0, 93, 186, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Orders',
                    'data' => array_map(fn (array $row): int => $row['orders'], $series),
                    'borderColor' => '#EC7725',
                    'backgroundColor' => 'rgba(236, 119, 37, 0.10)',
                    'tension' => 0.3,
                    'yAxisID' => 'yOrders',
                ],
            ],
            'labels' => array_map(fn (array $row): string => $row['label'], $series),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                ],
                'yOrders' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    // Orders are whole things; a gridline at 2.5 orders is a
                    // lie the eye believes.
                    'ticks' => ['precision' => 0],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    /**
     * One row per day, oldest first, with empty days filled in.
     *
     * Filling matters: without it a quiet Tuesday simply vanishes and the line
     * joins Monday to Wednesday, which reads as steady trade rather than a gap.
     *
     * @return list<array{date: string, label: string, revenue: float, orders: int}>
     */
    public static function series(int $days): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        // DATE() is the only grouping function MySQL and SQLite share — see
        // DECISIONS D-28.
        $rows = Order::query()
            ->revenue()
            ->where('placed_at', '>=', $start)
            ->selectRaw('DATE(placed_at) as day, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i);
            $key = $date->format('Y-m-d');
            $row = $rows->get($key);

            $series[] = [
                'date' => $key,
                'label' => $date->format('j M'),
                'revenue' => (float) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        return $series;
    }
}
