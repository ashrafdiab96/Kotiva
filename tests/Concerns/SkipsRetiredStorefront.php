<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Skips a test class that drove the retired Laravel storefront over HTTP.
 *
 * Since the Salla handoff (2026-10-13) /shop, /product/*, /cart and
 * /checkout redirect to Salla, and the cart and checkout mutations answer
 * 410 Gone. A class that builds its fixtures by POSTing to /cart/items, or
 * asserts on the shop, product, cart or checkout pages, can no longer run as
 * written. Its assertions are left exactly as they were — not weakened to
 * pass — and the class is deleted together with the commerce code.
 *
 * The behaviour that replaced it is pinned in SallaHandoffTest.
 */
trait SkipsRetiredStorefront
{
    protected function setUpSkipsRetiredStorefront(): void
    {
        $this->markTestSkipped(
            'Salla handoff: drives the retired Laravel storefront (shop/product/cart/checkout pages or the /cart and /checkout endpoints), which now redirect to Salla or answer 410. Delete with the commerce code.'
        );
    }
}
