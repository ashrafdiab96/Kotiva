<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A dashboard user.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property AdminRole $role
 * @property bool $is_active
 * @property CarbonInterface|null $last_login_at
 */
final class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The gate Filament calls before letting anyone into the panel.
     *
     * Deactivating an account has to lock it out immediately — otherwise
     * "is_active = false" is a label rather than a control.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /** @param Builder<Admin> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SuperAdmin;
    }

    public function canManageSettings(): bool
    {
        return $this->role->canManageSettings();
    }

    public function canManageAdmins(): bool
    {
        return $this->role->canManageAdmins();
    }

    public function canManageCatalog(): bool
    {
        return $this->role->canManageCatalog();
    }

    public function canViewOrders(): bool
    {
        return $this->role->canViewOrders();
    }

    public function canViewReports(): bool
    {
        return $this->role->canViewReports();
    }

    public function canDeleteRecords(): bool
    {
        return $this->role->canDeleteRecords();
    }

    public function canViewCustomers(): bool
    {
        return $this->role->canViewCustomers();
    }
}
