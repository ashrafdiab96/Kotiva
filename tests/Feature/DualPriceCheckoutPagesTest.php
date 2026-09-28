<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Support\Vat;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The money a shopper actually reads, page by page.
 *
 * The cart states things net, checkout states them gross, and the two have to
 * describe the same basket. The failure this guards against is a subtotal that
 * changes between the cart and the review step with nothing on screen
 * explaining why — the single most corrosive thing a shop can do to a person
 * about to hand over money.
 */
final class DualPriceCheckoutPagesTest extends TestCase
{
    use RefreshDatabase;

    private ?Cart $actingCart = null;

    /* ── the cart ─────────────────────────────────────────── */

    #[Test]
    public function the_cart_page_states_merchandise_net_then_vat_then_gross(): void
    {
        // 135.00 net / 155.25 gross, x2 = 270.00 net + 40.50 VAT = 310.50.
        $this->startCart($this->product(), 2);

        $response = $this->cartGet('/cart');

        $response->assertOk();
        $response->assertSee('SAR 135.00');          // unit price, net
        $response->assertSee('SAR 270.00');          // line total and subtotal, net
        $response->assertSee('SAR 40.50');           // the VAT line
        $response->assertSee('SAR 310.50');          // the inclusive total
        $response->assertSee('VAT (15%)');
        $response->assertSee('excl. VAT');
    }

    #[Test]
    public function the_cart_never_presents_the_net_subtotal_as_the_payable_amount(): void
    {
        /*
         | The panel's last figure must be the VAT-INCLUSIVE one. A net subtotal
         | sitting under a "Total" label is the exact mistake this whole change
         | could introduce: it reads as the amount due and is 15% short.
         */
        $this->startCart($this->product(), 2);

        $html = $this->cartGet('/cart')->getContent();

        $totalRow = strpos($html, 'cart-summary-total');
        $this->assertNotFalse($totalRow);

        $afterTotal = substr($html, $totalRow);
        $this->assertStringContainsString('SAR 310.50', $afterTotal, 'the total row carries the inclusive figure');
        $this->assertStringNotContainsString('SAR 270.00', $afterTotal, 'the net subtotal must not follow the total label');

        // And the page says what will happen to the VAT and the delivery.
        $this->cartGet('/cart')->assertSee('VAT is', false);
        $this->cartGet('/cart')->assertSee('delivery is added at checkout', false);
    }

    #[Test]
    public function the_cart_subtotal_is_quantity_times_the_net_price(): void
    {
        $cart = $this->startCart($this->product('33.33', '38.33'), 7);

        $this->assertSame('233.31', $cart->fresh()->subtotalExclVat());
        $this->assertSame('268.31', $cart->fresh()->subtotalInclVat());
        $this->assertSame('35.00', $cart->fresh()->totals()->productVat);
    }

    /* ── checkout ─────────────────────────────────────────── */

    #[Test]
    public function the_review_step_prices_items_inclusive_and_shows_the_full_breakdown(): void
    {
        $this->startCart($this->product(), 2);

        $response = $this->cartGet('/checkout/review');

        $response->assertOk();
        $response->assertSee('SAR 155.25');    // unit price, gross
        $response->assertSee('SAR 310.50');    // line total, gross
        $response->assertSee('Merchandise');
        $response->assertSee('SAR 270.00');    // merchandise net
        $response->assertSee('SAR 40.50');     // VAT
        $response->assertSee('incl. VAT');
    }

    #[Test]
    public function the_payment_step_shows_one_payable_total_that_includes_delivery(): void
    {
        $zone = $this->zone(fee: '25.00', threshold: null);
        $this->startCart($this->product(), 2);
        $this->postShipping($zone);

        $response = $this->cartGet('/checkout/payment');

        $response->assertOk();
        $response->assertSee('SAR 270.00');     // merchandise net
        $response->assertSee('SAR 310.50');     // merchandise gross
        $response->assertSee('SAR 25.00');      // delivery
        $response->assertSee('Total to pay');
        $response->assertSee('SAR 335.50');     // 310.50 + 25.00
        // VAT on goods (40.50) plus VAT inside the fee (3.26).
        $response->assertSee('SAR 43.76');
    }

    #[Test]
    public function the_breakdown_is_identical_on_every_step_before_payment(): void
    {
        // Three renders of the same basket. A figure that moves between them is
        // a figure the shopper cannot trust on any of them.
        $zone = $this->zone(fee: '25.00', threshold: null);
        $this->startCart($this->product(), 2);
        $this->postShipping($zone);

        foreach (['/checkout/shipping', '/checkout/payment'] as $uri) {
            $response = $this->cartGet($uri);
            $response->assertOk();
            $response->assertSee('SAR 270.00');
            $response->assertSee('SAR 310.50');
            $response->assertSee('SAR 43.76');
            $response->assertSee('SAR 335.50');
        }
    }

    #[Test]
    public function the_shipping_step_posts_no_amounts_at_all(): void
    {
        // The live delivery quote is priced from the server's cart, so there is
        // no subtotal in the form for a browser to edit.
        $this->startCart($this->product(), 1);

        $this->cartGet('/checkout/shipping')
            ->assertOk()
            ->assertDontSee('name="subtotal"', false);
    }

    #[Test]
    public function the_shipping_quote_endpoint_ignores_a_submitted_subtotal(): void
    {
        /*
         | This endpoint used to take `?subtotal=`, which let anyone ask it to
         | confirm free delivery on a basket that does not qualify. Nothing was
         | charged on that answer, but a shopper shown "Free" and then billed
         | 25.00 has been misled.
         */
        $zone = $this->zone(fee: '25.00', threshold: '300.00');
        $this->startCart($this->product('50.00', '57.50'), 1);

        $this->cartJson('GET', '/api/shipping/cities?zone_id='.$zone->id.'&subtotal=99999.00')
            ->assertOk()
            ->assertJsonPath('shipping.is_free', false)
            ->assertJsonPath('shipping.fee', '25.00');
    }

    #[Test]
    public function the_confirmation_page_reads_entirely_from_the_order_snapshot(): void
    {
        $zone = $this->zone(fee: '25.00', threshold: null);
        $product = $this->product();
        $cart = $this->startCart($product, 2);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        // The catalog moves on AFTER the order is placed.
        $product->update(['price_excl_vat' => '999.00', 'price_incl_vat' => '1148.85']);

        $response = $this->withSession(['checkout.orders' => [$order->order_no]])
            ->get('/checkout/confirmation/'.$order->order_no);

        $response->assertOk();
        $response->assertSee('SAR 270.00');     // merchandise net, as placed
        $response->assertSee('SAR 310.50');     // merchandise gross, as placed
        $response->assertSee('SAR 335.50');     // total paid
        $response->assertSee('VAT (15%)');
        // The new catalog prices must not reach a placed order.
        $response->assertDontSee('999.00');
        $response->assertDontSee('1148.85');
    }

    #[Test]
    public function the_confirmation_email_carries_the_same_breakdown(): void
    {
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($this->product(), 2);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $html = view('emails.order-placed-customer', [
            'order' => $order->fresh(['items', 'customer']),
            'deliveryEstimate' => null,
        ])->render();

        $this->assertStringContainsString('Merchandise (excl. VAT)', $html);
        $this->assertStringContainsString('SAR 270.00', $html);
        $this->assertStringContainsString('VAT (15%)', $html);
        $this->assertStringContainsString('SAR 43.76', $html);
        $this->assertStringContainsString('SAR 335.50', $html);
        $this->assertStringContainsString('SAR 155.25 incl. VAT', $html, 'line prices are what was charged');
    }

    #[Test]
    public function the_packing_slip_prints_both_unit_prices_and_splits_the_vat(): void
    {
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($this->product(), 2);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $html = view('pdf.packing-slip', ['order' => $order->fresh(['items', 'customer'])])->render();

        $this->assertStringContainsString('Unit excl. VAT', $html);
        $this->assertStringContainsString('Unit incl. VAT', $html);
        $this->assertStringContainsString('SAR 135.00', $html);
        $this->assertStringContainsString('SAR 155.25', $html);
        // The split an accountant needs: goods 40.50, delivery 3.26.
        $this->assertStringContainsString('SAR 40.50 on goods', $html);
        $this->assertStringContainsString('SAR 3.26 on delivery', $html);
    }

    #[Test]
    public function the_cod_amount_is_the_payable_total_including_vat_and_delivery(): void
    {
        // The single figure a courier collects. If anything in this change is
        // going to cost real money, it is this one.
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($this->product(), 2);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $this->assertSame('335.50', $order->grand_total);

        $slip = view('pdf.packing-slip', ['order' => $order->fresh(['items', 'customer'])])->render();
        $this->assertStringContainsString('Collect on Delivery', $slip);

        $email = view('emails.order-placed-customer', [
            'order' => $order->fresh(['items', 'customer']),
            'deliveryEstimate' => null,
        ])->render();
        $this->assertStringContainsString('<strong>SAR 335.50</strong> ready for the courier', $email);
    }

    /* ── fixtures ─────────────────────────────────────────── */

    private function product(string $excl = '135.00', string $incl = '155.25'): Product
    {
        return Product::factory()->create([
            'price_excl_vat' => $excl,
            'price_incl_vat' => $incl,
            'stock_qty' => 20,
        ]);
    }

    private function zone(string $fee = '25.00', ?string $threshold = '300.00'): ShippingZone
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create([
            'fee' => $fee,
            'free_shipping_threshold' => $threshold,
        ]);
        ShippingCity::factory()->for($zone, 'zone')->create();

        return $zone->fresh();
    }

    private function details(ShippingZone $zone): CheckoutDetails
    {
        $city = ShippingCity::query()->where('zone_id', $zone->id)->firstOrFail();

        return CheckoutDetails::fromValidated([
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => 'sara@example.com',
            'phone' => '+966512345678',
            'zone_id' => $zone->id,
            'city_id' => $city->id,
            'address_line1' => 'King Fahd Road',
            'district' => 'Al Olaya',
        ], $city->displayName());
    }

    private function postShipping(ShippingZone $zone): void
    {
        $city = ShippingCity::query()->where('zone_id', $zone->id)->firstOrFail();

        $this->call('POST', '/checkout/shipping', [
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => 'sara@example.com',
            'phone' => '0512345678',
            'zone_id' => $zone->id,
            'city_id' => $city->id,
            'address_line1' => 'King Fahd Road',
        ], $this->cartCookies())->assertRedirect('/checkout/payment');
    }

    private function startCart(Product $product, int $qty = 1): Cart
    {
        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();

        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->actingCart = $cart;

        return $cart;
    }

    /**
     * @return array<string, string>
     */
    private function cartCookies(): array
    {
        if (! $this->actingCart instanceof Cart) {
            return [];
        }

        $name = (string) config('kotiva.cart.cookie');

        return [$name => encrypt(
            CookieValuePrefix::create($name, app('encrypter')->getKey()).$this->actingCart->token,
            false
        )];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cartJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->call(
            method: $method,
            uri: $uri,
            cookies: $this->cartCookies(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $data === [] ? null : (string) json_encode($data),
        );
    }

    private function cartGet(string $uri): TestResponse
    {
        return $this->call(method: 'GET', uri: $uri, cookies: $this->cartCookies());
    }
}
