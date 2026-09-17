<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\GatedWidget;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Orders by status (§7.1).
 *
 * Visible to every role including staff, because progressing orders is exactly
 * what the staff role exists for — and the count of pending orders is the one
 * number that tells them there is work waiting.
 *
 * Terminal statuses with nothing in them are omitted: a row of zeroes for
 * "refunded" is noise on a dashboard that should be scannable at a glance.
 */
final class OrdersByStatusOverview extends StatsOverviewWidget
{
    use GatedWidget;

    protected static ?int $sort = 2;

    protected static function requiredCapability(): string
    {
        return 'canViewOrders';
    }

    protected function getStats(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [];

        foreach (OrderStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            // Keep the actionable ones visible even at zero; hide empty
            // terminal states.
            if ($count === 0 && $status->isTerminal()) {
                continue;
            }

            $stats[] = Stat::make($status->label(), (string) $count)
                ->color($status->color())
                ->url(OrderResourceUrl::forStatus($status));
        }

        return $stats;
    }
}
