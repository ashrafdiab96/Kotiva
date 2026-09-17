<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value store for the settings an admin edits at runtime.
 *
 * Reads are cached because the storefront touches several of these on every
 * request (cart TTL, COD toggle, announcement bar). Any write clears the cache,
 * so a dashboard change takes effect immediately rather than after a TTL.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
final class Setting extends Model
{
    private const CACHE_KEY = 'kotiva.settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /**
     * Falls back to config when no row exists, so a fresh database behaves
     * exactly like a configured one until an admin changes something.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all_();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::flush();
    }

    /**
     * @return array<string, mixed>
     */
    public static function all_(): array
    {
        /** @var array<string, mixed> $cached */
        $cached = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('value', 'key')->all()
        );

        return $cached;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        self::saved(fn () => self::flush());
        self::deleted(fn () => self::flush());
    }
}
