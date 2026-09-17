<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\GatedWidget;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Revenue today / 7 days / 30 days, and average order value (§7.1).
 *
 * "Revenue" is the four-status set from OrderStatus::revenueStatuses(), not the
 * brief's literal "delivered + confirmed" — see DECISIONS D-29. Taking the
 * brief literally would make the figure drop while orders sat in processing
 * and recover on delivery, so the total would sag exactly when the shop was
 * busiest at packing.
 *
 * Dated by `placed_at`, never `created_at`: the former is when the customer
 * actually committed.
 */
final class RevenueOverview extends StatsOverviewWidget
{
    use GatedWidget;

    protected static ?int $sort = 1;

    protected static function requiredCapability(): string
    {
        return 'canViewReports';
    }

    protected function getStats(): array
    {
        $currency = (string) config('kotiva.currency.code');

        $today = self::revenueSince(CarbonImmutable::now()->startOfDay());
        $week = self::revenueSince(CarbonImmutable::now()->subDays(6)->startOfDay());
        $month = self::revenueSince(CarbonImmutable::now()->subDays(29)->startOfDay());

        $monthOrders = self::orderCountSince(CarbonImmutable::now()->subDays(29)->startOfDay());
        $aov = $monthOrders > 0 ? $month / $monthOrders : 0.0;

        return [
            Stat::make('Revenue today', $currency.' '.number_format($today, 2))
                ->description('Orders placed since midnight')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Revenue, 7 days', $currency.' '.number_format($week, 2))
                ->description('Including today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                // Sparkline of the same window, so the number has a shape.
                ->chart(self::dailySeries(7)),

            Stat::make('Revenue, 30 days', $currency.' '.number_format($month, 2))
                ->description('Including today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->chart(self::dailySeries(30)),

            Stat::make('Average order value', $currency.' '.number_format($aov, 2))
                ->description($monthOrders.' order'.($monthOrders === 1 ? '' : 's').' in 30 days')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }

    private static function revenueSince(CarbonImmutable $from): float
    {
        return (float) Order::query()
            ->revenue()
            ->where('placed_at', '>=', $from)
            ->sum('grand_total');
    }

    private static function orderCountSince(CarbonImmutable $from): int
    {
        return Order::query()
            ->revenue()
            ->where('placed_at', '>=', $from)
            ->count();
    }

    /**
     * Daily revenue for the last N days, oldest first, with empty days as 0.0.
     *
     * Grouped with DATE() because it is the only date function both MySQL and
     * SQLite understand — the suite runs on SQLite (DECISIONS D-28), so
     * DATE_FORMAT() here would work in dev and fail in CI.
     *
     * @return array<float>
     */
    public static function dailySeries(int $days): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $rows = Order::query()
            ->revenue()
            ->where('placed_at', '>=', $start)
            ->selectRaw('DATE(placed_at) as day, SUM(grand_total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i)->format('Y-m-d');
            $series[] = (float) ($rows[$day] ?? 0);
        }

        return $series;
    }
}
