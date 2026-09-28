<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A newsletter signup.
 *
 * @property int $id
 * @property string $email
 * @property CarbonInterface|null $subscribed_at
 * @property CarbonInterface|null $unsubscribed_at
 * @property string|null $source
 */
final class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'subscribed_at',
        'unsubscribed_at',
        'source',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /** @param Builder<NewsletterSubscriber> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    /**
     * Re-subscribing clears a previous opt-out rather than creating a second
     * row — the unique index on email makes that the only correct behaviour.
     */
    public function resubscribe(): void
    {
        $this->forceFill([
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();
    }

    public function unsubscribe(): void
    {
        $this->forceFill(['unsubscribed_at' => now()])->save();
    }
}
