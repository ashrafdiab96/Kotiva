<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;

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

    public static function storeUrl(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    public static function cartUrl(): string
    {
        return (string) config('kotiva.salla.cart_url');
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
    public static function listingUrl(?string $filter = null, iterable $routineSkus = []): string
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

        return $query === []
            ? self::storeUrl()
            : self::storeUrl().'/?'.implode('&', $query);
    }

    /**
     * The product's own Salla page, or the store root until it is linked.
     */
    public static function productUrl(Product $product): string
    {
        return self::urlOrStore($product->salla_url);
    }

    /**
     * A stored Salla URL, falling back to the store root when there is none.
     */
    public static function urlOrStore(?string $sallaUrl): string
    {
        return is_string($sallaUrl) && $sallaUrl !== '' ? $sallaUrl : self::storeUrl();
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
        $store = parse_url(self::storeUrl());

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
