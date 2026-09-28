<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dashboard roles (§7).
 *
 * Three levels, and the boundaries are about damage rather than seniority:
 *   • super_admin — everything, including settings and other admins.
 *   • manager     — the whole shop, but cannot change settings or admins.
 *   • staff       — orders only, and only to read and progress them.
 *
 * Deliberately a small fixed set rather than a permission matrix: a shop this
 * size gains nothing from per-action grants except the chance to misconfigure
 * them.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Manager => 'warning',
            self::Staff => 'gray',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full access, including settings and admin accounts.',
            self::Manager => 'Catalog, orders, shipping and customers. No settings or admins.',
            self::Staff => 'Orders only — view and progress them.',
        };
    }

    /**
     * Settings and admin accounts are the two things that can quietly break
     * the whole shop, so they are super-admin only.
     */
    public function canManageSettings(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canManageAdmins(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Products, categories, shipping — anything that changes what is sold.
     */
    public function canManageCatalog(): bool
    {
        return $this !== self::Staff;
    }

    /**
     * Every role works orders — that is the entire point of the staff role.
     *
     * Stated explicitly rather than left implicit: a resource that nobody
     * remembered to gate is also reachable by everyone, and the two are
     * indistinguishable from the outside until the wrong person opens it.
     */
    public function canViewOrders(): bool
    {
        return true;
    }

    /**
     * Revenue, averages and the sales charts.
     *
     * Its own capability rather than borrowing canManageCatalog(): the two
     * happen to exclude the same role today, but "may edit products" and "may
     * see what the shop earns" are different questions, and a future role that
     * answers them differently should not have to untangle one from the other.
     */
    public function canViewReports(): bool
    {
        return $this !== self::Staff;
    }

    /**
     * Staff may progress an order but never delete one: an order is a record.
     */
    public function canDeleteRecords(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canViewCustomers(): bool
    {
        return $this !== self::Staff;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
