<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use Throwable;

/**
 * Builds a link from a dashboard stat to the orders table, pre-filtered.
 *
 * Its own class so a widget never has to construct Filament's table-filter
 * query string inline, and so a URL that cannot be built (no panel context,
 * for instance) degrades to no link rather than throwing on the dashboard.
 */
final class OrderResourceUrl
{
    public static function forStatus(OrderStatus $status): ?string
    {
        try {
            return OrderResource::getUrl('index', [
                'tableFilters' => [
                    'status' => ['values' => [$status->value]],
                ],
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
