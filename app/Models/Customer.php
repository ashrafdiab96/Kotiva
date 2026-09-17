<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guest buyer, recognised by email.
 *
 * There is no login in v1. This record exists so a second order from the same
 * person joins the first rather than vanishing into an anonymous receipt —
 * which is what lets the dashboard show repeat buyers and lifetime value.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property bool $marketing_opt_in
 */
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'marketing_opt_in',
    ];

    protected function casts(): array
    {
        return ['marketing_opt_in' => 'boolean'];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Normalise a Saudi mobile number to +9665XXXXXXXX.
     *
     * Accepts the forms people actually type — 05xxxxxxxx, 5xxxxxxxx,
     * +9665xxxxxxxx, 009665xxxxxxxx, with spaces or dashes. Storing one
     * canonical form is what stops the same buyer becoming two customers, and
     * what makes the dashboard's phone search work.
     *
     * Returns null when the input is not a valid KSA mobile.
     */
    public static function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        $digits = preg_replace('/^00966/', '+966', $digits) ?? $digits;
        $digits = preg_replace('/^966/', '+966', $digits) ?? $digits;

        if (str_starts_with($digits, '+966')) {
            $national = substr($digits, 4);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        // A KSA mobile is 5 followed by eight digits.
        if (preg_match('/^5\d{8}$/', $national) !== 1) {
            return null;
        }

        return '+966'.$national;
    }
}
