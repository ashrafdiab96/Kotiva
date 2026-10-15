<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReferralPartnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A doctor (or other partner) with a /dr/<code> share link.
 *
 * Salla owns the affiliate, its orders and its commission. This row maps our
 * short code onto the affiliate link Salla generated, so every Salla URL this
 * site emits for a referred visitor can carry that link's query string
 * (App\Support\Referral). See docs/DECISIONS.md D-38.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $salla_affiliate_url
 * @property string $landing_path
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class ReferralPartner extends Model
{
    /** @use HasFactory<ReferralPartnerFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Lowercase slug, 3–32 characters: "dr-ahmed". */
    public const CODE_PATTERN = '/^(?=.{3,32}$)[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** A path on this site: "/", "/science", "/routine-finder". */
    public const LANDING_PATH_PATTERN = '/^\/[a-z0-9\-\/]*$/';

    protected $fillable = [
        'name',
        'code',
        'salla_affiliate_url',
        'landing_path',
        'is_active',
        'notes',
    ];

    protected $attributes = [
        'landing_path' => '/',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ReferralVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(ReferralVisit::class);
    }

    /** @param Builder<ReferralPartner> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function isValidCode(mixed $code): bool
    {
        return is_string($code) && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /**
     * A relative path on this site. "//host" is refused even though the
     * character class allows it: a browser reads it as another origin.
     */
    public static function isValidLandingPath(mixed $path): bool
    {
        return is_string($path)
            && preg_match(self::LANDING_PATH_PATTERN, $path) === 1
            && ! str_starts_with($path, '//');
    }

    /**
     * Where /dr/<code> sends the visitor: the stored path, or "/" if a row was
     * written around the form's validation.
     */
    public function safeLandingPath(): string
    {
        return self::isValidLandingPath($this->landing_path) ? $this->landing_path : '/';
    }

    public function shareUrl(): string
    {
        return url('/dr/'.$this->code);
    }
}
