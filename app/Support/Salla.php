<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Every link from this site into the Salla storefront.
 *
 * The shop, product pages, cart and checkout all live on Salla now; this site
 * is the brand/content website. Views, controllers and the routine finder ask
 * this class for a URL rather than writing the store's host themselves, so the
 * store can move (or a staging store can be swapped in) by changing .env alone.
 * No view may contain the store hostname as a literal.
 *
 * Nothing here talks to the Salla API or reads the access token — that is the
 * kotiva:salla-links command's job, and only its.
 *
 * Doctor referrals (D-38): every public URL method passes its result through
 * Referral::applyTo(), so a visitor who arrived via /dr/<code> carries the
 * doctor's Salla affiliate query into the store. Pass withReferral: false
 * where a URL must be the same for everyone (crawl files, the dashboard).
 * Internally the bare origin is storeOrigin(), never storeUrl(), so a
 * referral query is never concatenated into the middle of another URL.
 */
final class Salla
{
    /**
     * The ten listing filters the Salla theme understands, in its own order.
     *
     * @var list<string>
     */
    public const FILTERS = [
        'all', 'cleanser', 'toner', 'lip-care', 'spf',
        'body', 'hand-care', 'treatment', 'serums', 'hair',
    ];

    /**
     * Filter keys this site used before the handoff, mapped onto the nearest
     * Salla key. "face" has no Salla equivalent; "cleanser" is the closest
     * existing key and is recorded in docs/DECISIONS.md for the client to
     * confirm. "sunscreen" was already an alias of "spf" here.
     *
     * @var array<string, string>
     */
    public const LEGACY_FILTERS = [
        'face' => 'cleanser',
        'treatments' => 'treatment',
        'sunscreen' => 'spf',
    ];

    public static function storeUrl(bool $withReferral = true): string
    {
        return self::referred(self::storeOrigin(), $withReferral);
    }

    public static function cartUrl(bool $withReferral = true): string
    {
        return self::referred((string) config('kotiva.salla.cart_url'), $withReferral);
    }

    /**
     * The current visitor's affiliate query, encoded ("ref=XXXX"), or "" when
     * there is none. For the one client-side builder that appends to a bare
     * store URL (the Routine Finder handoff); everything else gets the query
     * merged by the URL methods above.
     */
    public static function referralQuery(): string
    {
        $marker = self::storeOrigin().'/';
        $referred = self::referred($marker, true);

        return $referred === $marker ? '' : (string) parse_url($referred, PHP_URL_QUERY);
    }

    /**
     * The configured store URL with nothing added: the base every other URL
     * here is built on.
     */
    private static function storeOrigin(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    /**
     * $url with the current visitor's referral, when there is a request and
     * the caller wants it.
     */
    private static function referred(string $url, bool $withReferral): string
    {
        if (! $withReferral || ! app()->bound('request')) {
            return $url;
        }

        return Referral::applyTo($url, Referral::current(app(Request::class)));
    }

    public static function redirectStatus(): int
    {
        $status = (int) config('kotiva.salla.redirect_status', 302);

        // Only the two statuses the operator is meant to choose between; a
        // typo in .env must not turn every shop link into a 200 or a 500.
        return in_array($status, [301, 302, 307, 308], true) ? $status : 302;
    }

    /**
     * The Salla listing, optionally filtered and/or carrying a routine.
     *
     * Unknown filters are dropped rather than passed through: Salla would show
     * an empty grid for a key it does not know, which reads as "out of stock".
     *
     * @param  iterable<mixed>  $routineSkus
     */
    public static function listingUrl(?string $filter = null, iterable $routineSkus = [], bool $withReferral = true): string
    {
        $query = [];

        $key = self::filterKey($filter);

        if ($key !== null) {
            $query[] = 'filter='.$key;
        }

        $skus = self::normaliseSkus($routineSkus);

        if ($skus !== []) {
            // Commas left literal: the Salla theme splits on ",", and every SKU
            // is already restricted to [A-Z0-9_-], so nothing needs escaping.
            $query[] = 'routine='.implode(',', $skus);
        }

        return self::referred($query === []
            ? self::storeOrigin()
            : self::storeOrigin().'/?'.implode('&', $query), $withReferral);
    }

    /**
     * The product's own Salla page, or the store root until it is linked.
     *
     * Products store only the path (products.salla_path); the store origin
     * comes from config. This is the only place the two are joined, so moving
     * the store is an .env change and nothing else.
     */
    public static function productUrl(Product $product, bool $withReferral = true): string
    {
        $path = $product->salla_path;

        return self::referred(self::isStorePath($path)
            ? rtrim(self::storeOrigin(), '/').$path
            : self::storeOrigin(), $withReferral);
    }

    /**
     * Whether a stored value is a usable store path: absolute ("/…"), not
     * protocol-relative ("//host"), and free of whitespace and control
     * characters — so a bad row cannot point a link at another host.
     */
    public static function isStorePath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && preg_match('/[\s\x00-\x1F\x7F\\\\]/', $path) !== 1;
    }

    /**
     * The path of a full URL on the configured store, or null when the URL is
     * on another origin. A path prefix on the store URL (a store served from
     * "https://host/shop") is stripped too, so productUrl() reassembles it.
     */
    public static function pathFromStoreUrl(string $url): ?string
    {
        if (! self::isStoreUrl($url)) {
            return null;
        }

        $prefix = rtrim((string) parse_url(self::storeOrigin(), PHP_URL_PATH), '/');
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if ($prefix !== '') {
            $path = substr($path, strlen($prefix));
        }

        $path = ($path === '' ? '/' : $path).(is_string($query) && $query !== '' ? '?'.$query : '');

        return self::isStorePath($path) ? $path : null;
    }

    /**
     * Map a requested filter onto a Salla key, or null to drop it.
     */
    public static function filterKey(?string $filter): ?string
    {
        if ($filter === null) {
            return null;
        }

        $filter = strtolower(trim($filter));
        $filter = self::LEGACY_FILTERS[$filter] ?? $filter;

        return in_array($filter, self::FILTERS, true) ? $filter : null;
    }

    /**
     * Upper-cased, de-duplicated SKUs; anything that is not a plausible SKU is
     * discarded so a query string cannot inject into the redirect target.
     *
     * @param  iterable<mixed>  $skus
     * @return list<string>
     */
    public static function normaliseSkus(iterable $skus): array
    {
        $out = [];

        foreach ($skus as $sku) {
            if (! is_string($sku)) {
                continue;
            }

            $sku = strtoupper(trim($sku));

            if ($sku !== '' && preg_match('/^[A-Z0-9_-]{1,64}$/', $sku) === 1 && ! in_array($sku, $out, true)) {
                $out[] = $sku;
            }
        }

        return $out;
    }

    /**
     * Whether a URL is an absolute http(s) URL on the configured store.
     *
     * Compared on scheme + host (and the store's path prefix, if it has one)
     * rather than a string prefix, so "https://shop.example.com.evil.test"
     * does not pass for "https://shop.example.com".
     */
    public static function isStoreUrl(string $url): bool
    {
        $candidate = parse_url($url);
        $store = parse_url(self::storeOrigin());

        if (! is_array($candidate) || ! is_array($store)) {
            return false;
        }

        $scheme = strtolower((string) ($candidate['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || ! isset($candidate['host'], $store['host'])) {
            return false;
        }

        if ($scheme !== strtolower((string) ($store['scheme'] ?? 'https'))
            || strtolower($candidate['host']) !== strtolower($store['host'])) {
            return false;
        }

        $prefix = rtrim((string) ($store['path'] ?? ''), '/');

        return $prefix === '' || str_starts_with((string) ($candidate['path'] ?? ''), $prefix.'/');
    }
}
