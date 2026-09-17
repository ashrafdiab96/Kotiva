<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * Role gating for a Filament resource.
 *
 * Hiding a resource from the navigation is cosmetic — `canViewAny()` is what
 * actually refuses the route when someone types the URL. Both are driven from
 * one capability here so a resource cannot end up hidden but reachable, which
 * is the failure that looks safe in a screenshot and is not.
 *
 * Deletion is separately gated: a manager runs the shop day to day but must
 * not be able to destroy records, several of which (orders, stock movements)
 * are the shop's own audit trail.
 */
trait GatedByRole
{
    /**
     * The Admin capability method this resource requires, e.g.
     * 'canManageCatalog'. Resources override this.
     */
    protected static function requiredCapability(): string
    {
        return 'canManageCatalog';
    }

    protected static function currentAdmin(): ?Admin
    {
        $user = auth()->user();

        return $user instanceof Admin ? $user : null;
    }

    protected static function adminCan(string $capability): bool
    {
        $admin = self::currentAdmin();

        if (! $admin instanceof Admin || ! method_exists($admin, $capability)) {
            return false;
        }

        return (bool) $admin->{$capability}();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::adminCan(static::requiredCapability());
    }

    public static function canViewAny(): bool
    {
        return self::adminCan(static::requiredCapability());
    }

    public static function canView(Model $record): bool
    {
        return self::adminCan(static::requiredCapability());
    }

    public static function canCreate(): bool
    {
        return self::adminCan(static::requiredCapability());
    }

    public static function canEdit(Model $record): bool
    {
        return self::adminCan(static::requiredCapability());
    }

    public static function canDelete(Model $record): bool
    {
        return self::adminCan(static::requiredCapability())
            && self::adminCan('canDeleteRecords');
    }

    public static function canDeleteAny(): bool
    {
        return self::adminCan(static::requiredCapability())
            && self::adminCan('canDeleteRecords');
    }
}
