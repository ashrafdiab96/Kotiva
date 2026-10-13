<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\Admin;

/**
 * Role gating for a dashboard widget.
 *
 * Filament's Page::filterVisibleWidgets() consults canView(), and Widget's own
 * CanAuthorizeAccess aborts 403 on direct access — so one override does both,
 * and a widget cannot end up hidden from the grid yet still reachable.
 */
trait GatedWidget
{
    /**
     * The Admin capability this widget requires.
     */
    protected static function requiredCapability(): string
    {
        return 'canViewOrders';
    }

    public static function canView(): bool
    {
        // Every gated widget reports on Laravel's orders, revenue or stock,
        // which Salla owns now. Hidden for every role while commerce is off;
        // the dashboard shows the SallaNotice widget instead.
        if (! config('kotiva.commerce_enabled', false)) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof Admin) {
            return false;
        }

        $capability = static::requiredCapability();

        return method_exists($user, $capability) && (bool) $user->{$capability}();
    }
}
