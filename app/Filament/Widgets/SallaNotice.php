<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\Salla;
use Filament\Widgets\Widget;

/**
 * The dashboard's first card since the Salla handoff: where the shop went.
 *
 * Shown to every role. The revenue, order and low-stock widgets it replaces
 * are hidden while kotiva.commerce_enabled is off, and without this an admin
 * opening the dashboard would see an empty page with no idea why.
 */
final class SallaNotice extends Widget
{
    protected static ?int $sort = -10;

    // Rendered with the page, not lazy-loaded: it runs no query, and a
    // placeholder box is exactly the "empty dashboard" this card exists to avoid.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.salla-notice';

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        $dashboard = (string) config('kotiva.salla.dashboard_url');
        $store = Salla::storeUrl();

        return [
            'storeUrl' => $store,
            'storeHost' => (string) (parse_url($store, PHP_URL_HOST) ?: $store),
            'manageUrl' => $dashboard !== '' ? $dashboard : $store,
        ];
    }
}
