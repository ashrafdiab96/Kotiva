<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| KOTIVA storefront configuration
|--------------------------------------------------------------------------
|
| Build-time / deploy-time constants only. Anything the client is expected to
| change from the dashboard at runtime lives in the `settings` table instead
| (store email, COD toggle, cart TTL, announcement bar, VAT rate override).
| Values here are the fallbacks used before a setting row exists.
|
*/

return [

    // Canonical origin used for canonical URLs, Open Graph tags, JSON-LD and
    // the sitemap. The legacy generators took this from KOTIVA_SITE_ORIGIN and
    // the whole static site was audited for a single origin — keep that rule.
    'site_origin' => rtrim((string) env('KOTIVA_SITE_ORIGIN', 'https://kotiva.co'), '/'),

    'currency' => [
        'code' => 'SAR',
        // The storefront prints "SAR 155.25" — symbol before amount, space, 2dp.
        'decimals' => 2,
    ],

    // Prices are stored and displayed VAT-inclusive. This rate is only used to
    // compute the informational VAT portion shown on orders and invoices.
    'vat_rate' => (float) env('KOTIVA_VAT_RATE', 0.15),

    'order_number' => [
        'prefix' => 'KOT-',
        'length' => 6,
        // 0/O and 1/I deliberately excluded — these numbers get read aloud on
        // the phone for cash-on-delivery confirmations.
        'alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'max_attempts' => 5,
    ],

    'cart' => [
        'cookie' => 'kotiva_cart',
        'ttl_hours' => (int) env('KOTIVA_CART_TTL_HOURS', 72),
        // Hard ceiling per line, independent of stock.
        'max_qty_per_line' => 10,
    ],

    'stock' => [
        'low_stock_threshold' => (int) env('KOTIVA_LOW_STOCK_THRESHOLD', 5),
        // A product may only raise one low-stock alert per this window.
        'low_stock_alert_debounce_hours' => 24,
    ],

    /*
     | The first super-admin, seeded on a fresh install.
     |
     | Read through config rather than env() at the call site: once
     | `config:cache` has run in production, env() returns null everywhere
     | outside this directory — so seeding straight from env would silently
     | create no admin account at all on exactly the install that needs one.
     */
    'admin' => [
        'name' => env('KOTIVA_ADMIN_NAME', 'KOTIVA Admin'),
        'email' => env('KOTIVA_ADMIN_EMAIL', ''),
        'password' => env('KOTIVA_ADMIN_PASSWORD', ''),
    ],

    'mail' => [
        'store_email' => env('KOTIVA_STORE_EMAIL', 'info@vitakode.com'),
        'admin_notification_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('KOTIVA_ADMIN_EMAILS', 'info@vitakode.com'))
        ))),
    ],

    'shop' => [
        /*
         | The filter pills are a CURATED taxonomy, not a derived one: products
         | also carry a "cleanser" tag that never had a pill, and the "sunscreen"
         | tag is labelled "SPF". Deriving the row from the data would invent an
         | eighth pill nobody designed. Order and labels are the legacy row's.
         | Counts are computed server-side from filter_tags.
         */
        'filters' => [
            'all' => 'All',
            'face' => 'Face',
            'body' => 'Body',
            'serums' => 'Serums',
            'treatments' => 'Treatments',
            'spf' => 'SPF',
            'hair' => 'Hair',
        ],

        /*
         | The static site's SPF pill was data-filter="sunscreen" while the
         | product data tags those three products "spf". Both sides were
         | hand-written, so the mismatch never showed: the pill matched the
         | cards' hand-typed data-tags, not the catalog. Rendering from the
         | catalog exposes it. "spf" wins because it is what the data says;
         | "sunscreen" is kept as an alias because footer links, the old
         | breadcrumb JSON-LD and any indexed ?filter=sunscreen URL use it.
         */
        'filter_aliases' => [
            'sunscreen' => 'spf',
        ],

        'sorts' => [
            'featured' => 'Featured',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'name_asc' => 'Name: A–Z',
            'newest' => 'Newest',
        ],

        'default_sort' => 'featured',

        'related_limit' => 4,
    ],

    /*
     | Breadcrumb position 2 for a product page. The zone is the product's first
     | filter tag, and the pair must agree with the shop's own pill taxonomy —
     | note "spf" the tag maps to the "sunscreen" filter labelled "SPF".
     | Carried over verbatim from the retired generator's ZONE_CRUMBS.
     */
    'zone_crumbs' => [
        'face' => ['name' => 'Face', 'filter' => 'face'],
        'body' => ['name' => 'Body', 'filter' => 'body'],
        'serums' => ['name' => 'Serums', 'filter' => 'serums'],
        'hair' => ['name' => 'Hair', 'filter' => 'hair'],
        'spf' => ['name' => 'SPF', 'filter' => 'sunscreen'],
    ],
];
