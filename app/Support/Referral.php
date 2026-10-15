<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReferralPartner;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Doctor referral attribution, carried into Salla as a query string.
 *
 * A visitor who arrives through /dr/<code> gets the kotiva_ref cookie
 * (encrypted, httpOnly, read only here). While it names an active partner,
 * every Salla URL this site emits carries the query string of that partner's
 * Salla affiliate link, and Salla credits the order to the affiliate. Salla
 * owns orders and commission; nothing here counts them. See D-38.
 *
 * The affiliate parameter's name and value are whatever Salla generated and
 * are treated as opaque.
 */
final class Referral
{
    /** Request attribute holding current()'s answer for the rest of the request. */
    private const CACHE_ATTRIBUTE = '_kotiva_referral_partner';

    public static function cookieName(): string
    {
        return 'kotiva_ref';
    }

    /** 90 days, in minutes, as Cookie::make() takes it. */
    public static function cookieMinutes(): int
    {
        return 60 * 24 * 90;
    }

    /**
     * The query parameters of the partner's Salla affiliate link.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the link is not on the configured
     *                                  Salla store or carries no query string
     */
    public static function affiliateQuery(ReferralPartner $partner): array
    {
        return self::queryOf($partner->salla_affiliate_url);
    }

    /**
     * The same check, on a raw URL — used by the dashboard form before a
     * partner exists.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function queryOf(string $affiliateUrl): array
    {
        $parts = parse_url(trim($affiliateUrl));
        $storeHost = parse_url((string) config('kotiva.salla.store_url'), PHP_URL_HOST);

        if (! is_array($parts) || ! isset($parts['host']) || ! is_string($storeHost)
            || strtolower($parts['host']) !== strtolower($storeHost)) {
            throw new InvalidArgumentException(
                'The affiliate link must be on the Salla store ('.(is_string($storeHost) ? $storeHost : 'not configured').').'
            );
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        /** @var array<string, mixed> $query */
        $query = array_filter($query, fn (mixed $v, int|string $k): bool => $k !== '', ARRAY_FILTER_USE_BOTH);

        if ($query === []) {
            throw new InvalidArgumentException(
                'The affiliate link has no query string. Copy the full link from Salla\'s Affiliate marketing screen.'
            );
        }

        return $query;
    }

    /**
     * The active partner named by the request's cookie, or null. Looked up
     * once per request.
     */
    public static function current(Request $request): ?ReferralPartner
    {
        if ($request->attributes->has(self::CACHE_ATTRIBUTE)) {
            $cached = $request->attributes->get(self::CACHE_ATTRIBUTE);

            return $cached instanceof ReferralPartner ? $cached : null;
        }

        $code = $request->cookie(self::cookieName());

        $partner = ReferralPartner::isValidCode($code)
            ? ReferralPartner::query()->active()->where('code', $code)->first()
            : null;

        self::remember($request, $partner);

        return $partner;
    }

    /**
     * Pin the partner for the rest of this request. /dr/<code> calls it so a
     * redirect built in the same request already carries the new doctor,
     * whatever cookie the browser arrived with.
     */
    public static function remember(Request $request, ?ReferralPartner $partner): void
    {
        $request->attributes->set(self::CACHE_ATTRIBUTE, $partner ?? false);
    }

    /**
     * $url with the partner's affiliate query merged in.
     *
     * Existing parameters (filter, routine) are kept byte-for-byte — including
     * the literal commas in routine= that the Salla theme splits on — and an
     * affiliate key replaces any parameter of the same name. URLs not on the
     * Salla store, and partners whose link no longer validates (the store host
     * changed in .env), come back unchanged: a broken referral must never break
     * a shop link.
     */
    public static function applyTo(string $url, ?ReferralPartner $partner): string
    {
        if (! $partner instanceof ReferralPartner || ! Salla::isStoreUrl($url)) {
            return $url;
        }

        try {
            $affiliate = self::affiliateQuery($partner);
        } catch (InvalidArgumentException) {
            return $url;
        }

        $fragment = '';
        $hash = strpos($url, '#');

        if ($hash !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $existing = '';
        $mark = strpos($url, '?');

        if ($mark !== false) {
            $existing = substr($url, $mark + 1);
            $url = substr($url, 0, $mark);
        }

        $kept = array_values(array_filter(
            explode('&', $existing),
            fn (string $pair): bool => $pair !== '' && ! array_key_exists(self::keyOf($pair), $affiliate),
        ));

        $kept[] = http_build_query($affiliate, '', '&', PHP_QUERY_RFC3986);

        // "https://store" and "https://store/" are the same page; the slash
        // keeps the shape listingUrl() already uses ("…/?filter=").
        if (parse_url($url, PHP_URL_PATH) === null) {
            $url .= '/';
        }

        return $url.'?'.implode('&', $kept).$fragment;
    }

    /**
     * The decoded base name of one "k=v" pair: "a%5B%5D=1" and "a[]=1" are
     * both "a", matching what parse_str() makes of them.
     */
    private static function keyOf(string $pair): string
    {
        $key = urldecode(explode('=', $pair, 2)[0]);
        $bracket = strpos($key, '[');

        return $bracket === false ? $key : substr($key, 0, $bracket);
    }
}
