# {{ parse_url($origin, PHP_URL_HOST) }} — KOTIVA brand/content website.
# The store is on Salla ({{ parse_url(\App\Support\Salla::storeUrl(withReferral: false), PHP_URL_HOST) }}), which publishes its own robots.txt.
#
# /shop and /product/ stay ALLOWED on purpose: they now redirect to the Salla
# store, and a crawler can only follow (and transfer signals through) a
# redirect it is allowed to fetch.
User-agent: *
Disallow: /cart
Disallow: /checkout
Disallow: /api/
Disallow: /admin

Sitemap: {{ $origin }}/sitemap.xml
