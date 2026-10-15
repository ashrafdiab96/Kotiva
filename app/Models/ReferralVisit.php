<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One hit on /dr/<code>.
 *
 * ip_hash and user_agent are kept for de-duplication only and are never shown
 * in the dashboard.
 *
 * @property int $id
 * @property int $referral_partner_id
 * @property Carbon $visited_at
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property string|null $referer
 * @property string $landing_path
 * @property bool $is_unique_day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ReferralVisit extends Model
{
    protected $fillable = [
        'referral_partner_id',
        'visited_at',
        'ip_hash',
        'user_agent',
        'referer',
        'landing_path',
        'is_unique_day',
    ];

    protected $hidden = [
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'is_unique_day' => 'boolean',
        ];
    }

    /** @return BelongsTo<ReferralPartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(ReferralPartner::class, 'referral_partner_id');
    }

    /**
     * sha256 of the address and APP_KEY, so the table cannot be reversed into
     * IPs with a rainbow table of the IPv4 space unless the key leaks too.
     */
    public static function hashIp(?string $ip): string
    {
        return hash('sha256', (string) $ip.(string) config('app.key'));
    }
}
