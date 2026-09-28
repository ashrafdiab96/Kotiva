<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the storefront shows, and what it must never show.
 *
 * The whole change hinges on one rule: the shop leads with the VAT-EXCLUSIVE
 * price and says so, and checkout charges the VAT-INCLUSIVE one. A page that
 * prints the wrong side of that is not a cosmetic bug — it is a shopper being
 * quoted one figure and billed another.
 */
final class DualPriceStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $excl = '135.00', string $incl = '155.25', array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'price_excl_vat' => $excl,
            'price_incl_vat' => $incl,
            'stock_qty' => 10,
        ]);
    }

    #[Test]
    public function the_shop_listing_shows_the_exclusive_price_and_labels_it(): void
    {
        $this->product();

        $response = $this->get('/shop');

        $response->assertOk();
        $response->assertSee('SAR 135.00');
        $response->assertSee('excl. VAT');
        // The inclusive price is checkout's figure, not the listing's.
        $response->assertDontSee('SAR 155.25');
    }

    #[Test]
    public function the_product_page_shows_both_prices_with_the_exclusive_one_leading(): void
    {
        $product = $this->product();

        $response = $this->get('/product/'.$product->slug);

        $response->assertOk();
        $response->assertSee('SAR 135.00');
        $response->assertSee('excl. VAT');
        // Stated on the page rather than saved for checkout: nobody should meet
        // the amount they will be charged for the first time on the payment step.
        $response->assertSee('SAR 155.25 including 15% VAT');
    }

    #[Test]
    public function the_product_pages_structured_data_states_the_price_it_displays(): void
    {
        /*
         | A crawler compares the markup against the visible price. The page now
         | leads with the exclusive figure, so the Offer has to say that figure
         | AND say it is tax-exclusive — carrying the old inclusive number would
         | be a mismatch, and carrying the exclusive number while still claiming
         | valueAddedTaxIncluded would be a lie.
         */
        $product = $this->product();

        $response = $this->get('/product/'.$product->slug);

        $response->assertSee('"price":"135.00"', false);
        $response->assertSee('"valueAddedTaxIncluded":false', false);
    }

    #[Test]
    public function the_home_page_rail_shows_the_exclusive_price_and_labels_it(): void
    {
        $this->product(attributes: ['is_best_seller' => true, 'is_active' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('SAR 135.00');
        // Visible, not only in the aria-label: a sighted shopper needs the
        // qualifier as much as a screen-reader user does.
        $response->assertSee('excl. VAT');
        $response->assertSee('SAR 135.00 excluding VAT', false);
    }

    #[Test]
    public function related_products_show_the_same_labelled_exclusive_price(): void
    {
        $category = Category::factory()->create();
        $product = $this->product(attributes: ['category_id' => $category->getKey()]);
        $this->product('200.00', '230.00', ['category_id' => $category->getKey()]);

        $response = $this->get('/product/'.$product->slug);

        $response->assertOk();
        $response->assertSee('SAR 200.00');
        $response->assertDontSee('SAR 230.00');
    }

    #[Test]
    public function the_products_api_serves_the_exclusive_price_and_names_which_it_is(): void
    {
        // The Routine Finder reads this. Its recommendations sit beside the
        // shop's own cards, so it has to quote the same side of the VAT line.
        // A net price with a decimal part, so the assertion is about the value
        // and not about how JSON happens to render a whole number.
        $this->product('134.50', '154.68');

        $this->get('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.price', 134.5)
            ->assertJsonPath('data.0.price_vat', 'excluded')
            ->assertJsonPath('data.0.price_excl_vat', 134.5)
            ->assertJsonPath('data.0.price_incl_vat', 154.68);
    }

    #[Test]
    public function price_sorting_uses_the_displayed_exclusive_price(): void
    {
        /*
         | Sorting on the inclusive price would give the same order today, but
         | only because every product shares one rate. These three are built so
         | the two orders DIFFER: the cheapest net product carries the highest
         | gross price. Sorting on the wrong column would put the cards in an
         | order the visible numbers contradict.
         */
        $this->product('100.00', '200.00', ['name' => 'Aaa Cheapest Net']);
        $this->product('150.00', '160.00', ['name' => 'Bbb Middle']);
        $this->product('200.00', '210.00', ['name' => 'Ccc Dearest Net']);

        $ascending = $this->get('/shop?sort=price_asc');
        $ascending->assertOk();

        $order = $this->positionsOf($ascending->getContent(), ['Aaa Cheapest Net', 'Bbb Middle', 'Ccc Dearest Net']);
        $this->assertSame([0, 1, 2], $this->ranks($order), 'ascending by the exclusive price');

        $descending = $this->get('/shop?sort=price_desc');
        $order = $this->positionsOf($descending->getContent(), ['Ccc Dearest Net', 'Bbb Middle', 'Aaa Cheapest Net']);
        $this->assertSame([0, 1, 2], $this->ranks($order), 'descending by the exclusive price');
    }

    #[Test]
    public function the_mini_cart_states_net_vat_and_gross(): void
    {
        // The drawer renders from the summary payload and does no arithmetic,
        // so what it can show is decided by what the server sends.
        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-mini-cart-subtotal', false)
            ->assertSee('data-mini-cart-vat', false)
            ->assertSee('data-mini-cart-total', false)
            ->assertSee('Product prices exclude VAT');
    }

    /**
     * @param  list<string>  $needles
     * @return list<int>
     */
    private function positionsOf(string $html, array $needles): array
    {
        return array_map(function (string $needle) use ($html): int {
            $at = strpos($html, $needle);
            $this->assertNotFalse($at, "'{$needle}' is not on the page");

            return $at;
        }, $needles);
    }

    /**
     * @param  list<int>  $positions
     * @return list<int>
     */
    private function ranks(array $positions): array
    {
        $sorted = $positions;
        sort($sorted);

        return array_map(fn (int $p): int => (int) array_search($p, $sorted, true), $positions);
    }
}
