<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\Setting;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\StockService;
use App\Support\OrderNumber;
use App\Support\Vat;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Checkout: the point where stock, money and a promise to a customer all meet.
 *
 * The domain assertions drive CheckoutService directly; the guard assertions go
 * through HTTP, because redirects and session gating are the behaviour being
 * checked there.
 */
final class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private ?Cart $actingCart = null;

    /**
     * A product priced from its VAT-EXCLUSIVE figure, with the inclusive price
     * derived at 15% unless the caller states one.
     *
     * Net-first, because that is how the client's brochures are built: the
     * exclusive prices are round riyal figures whose inclusive counterparts are
     * exact at two decimals. Driving these tests from a round INCLUSIVE price
     * instead would give every product a net price ending in a third decimal
     * and put a rounding halala into assertions that are supposed to be about
     * something else.
     */
    private function product(int $stock = 10, string $exclVat = '150.00', ?string $inclVat = null): Product
    {
        $product = Product::factory()->withStock(0)->create([
            'price_excl_vat' => $exclVat,
            'price_incl_vat' => $inclVat ?? Vat::inclusiveOf($exclVat),
        ]);
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    private function zone(string $fee = '25.00', ?string $threshold = '300.00'): ShippingZone
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create([
            'fee' => $fee,
            'free_shipping_threshold' => $threshold,
        ]);
        ShippingCity::factory()->for($zone, 'zone')->create(['name_en' => 'Riyadh']);

        return $zone->fresh();
    }

    private function details(ShippingZone $zone, string $email = 'sara@example.com'): CheckoutDetails
    {
        $city = ShippingCity::query()->where('zone_id', $zone->id)->firstOrFail();

        return CheckoutDetails::fromValidated([
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => $email,
            'phone' => '+966512345678',
            'zone_id' => $zone->id,
            'city_id' => $city->id,
            'address_line1' => 'King Fahd Road',
            'district' => 'Al Olaya',
            'marketing_opt_in' => true,
        ], $city->displayName());
    }

    /**
     * Put a product in a cart through the real endpoint, and keep the cookie.
     */
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function cartForm(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->call(method: $method, uri: $uri, parameters: $data, cookies: $this->cartCookies());
    }

    private function cartGet(string $uri): TestResponse
    {
        return $this->call(method: 'GET', uri: $uri, cookies: $this->cartCookies());
    }

    /* ── the happy path ───────────────────────────────────── */

    #[Test]
    public function placing_an_order_records_everything_the_order_needs_to_stand_alone(): void
    {
        // 150.00 net / 172.50 gross, x2 = 300.00 net / 345.00 gross, which hits
        // the free-shipping threshold EXACTLY on the configured basis.
        $product = $this->product(stock: 10, exclVat: '150.00');
        $zone = $this->zone(fee: '25.00', threshold: '345.00');
        $cart = $this->startCart($product, 2);

        $order = app(CheckoutService::class)->place(
            cart: $cart,
            details: $this->details($zone),
            gateway: app(CashOnDeliveryGateway::class),
        );

        $this->assertTrue(OrderNumber::isValid($order->order_no));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status, 'COD is not paid at checkout');

        $this->assertSame('300.00', $order->subtotal_excl_vat);
        $this->assertSame('345.00', $order->subtotal, 'subtotal stays VAT-INCLUSIVE');
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertSame('345.00', $order->grand_total);

        // VAT is the difference between the two authoritative prices, and it is
        // CONTAINED in the total rather than added to it.
        $this->assertSame('45.00', $order->product_vat_amount);
        $this->assertSame('0.00', $order->shipping_vat_amount, 'free delivery carries no VAT');
        $this->assertSame('45.00', $order->vat_amount);
        $this->assertSame('0.150000', $order->vat_rate, 'the rate is snapshotted with the order');
        $this->assertTrue($order->totalsReconcile());

        $item = $order->items->firstOrFail();
        $this->assertSame($product->sku, $item->sku_snapshot);
        $this->assertSame($product->name, $item->name_snapshot);
        $this->assertSame('172.50', $item->unit_price, 'the line is charged VAT-inclusive');
        $this->assertSame('150.00', $item->unit_price_excl_vat);
        $this->assertSame(2, $item->qty);
        $this->assertSame('345.00', $item->line_total);
        $this->assertSame('300.00', $item->line_total_excl_vat);
        $this->assertSame('45.00', $item->vat_amount);
        $this->assertSame('0.150000', $item->vat_rate);
    }

    #[Test]
    public function the_shipping_fee_applies_below_the_free_threshold(): void
    {
        // 100.00 net / 115.00 gross — well under the threshold on either basis.
        $product = $this->product(stock: 10, exclVat: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: '300.00');
        $cart = $this->startCart($product, 1);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $this->assertSame('100.00', $order->subtotal_excl_vat);
        $this->assertSame('115.00', $order->subtotal);
        $this->assertSame('25.00', $order->shipping_fee);
        $this->assertSame('140.00', $order->grand_total);

        // The delivery fee is VAT-inclusive under the default treatment, so its
        // VAT is EXTRACTED from the fee — 25.00 x 0.15 / 1.15 — and the payable
        // total is unchanged by it.
        $this->assertSame('15.00', $order->product_vat_amount);
        $this->assertSame('3.26', $order->shipping_vat_amount);
        $this->assertSame('18.26', $order->vat_amount);
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function the_free_shipping_threshold_is_measured_on_the_configured_basis(): void
    {
        /*
         | The cart now shows a VAT-exclusive total and checkout a VAT-inclusive
         | one, so "the subtotal" a threshold is compared against is a real
         | choice rather than an obvious one. A 300.00 threshold that silently
         | meant 300.00 net on one page and 300.00 gross on another would give
         | away delivery on baskets that do not qualify.
         |
         | 280.00 net / 322.00 gross sits either side of a 300.00 threshold, so
         | the two bases give opposite answers on the same basket.
         */
        $zone = $this->zone(fee: '25.00', threshold: '300.00');

        Setting::put('free_shipping_basis', Vat::BASIS_INCLUSIVE);
        $order = app(CheckoutService::class)->place(
            $this->startCart($this->product(stock: 10, exclVat: '280.00'), 1),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );
        $this->assertSame('0.00', $order->shipping_fee, '322.00 gross clears a 300.00 gross threshold');
        $this->assertSame('322.00', $order->grand_total);

        Setting::put('free_shipping_basis', Vat::BASIS_EXCLUSIVE);
        $order = app(CheckoutService::class)->place(
            $this->startCart($this->product(stock: 10, exclVat: '280.00'), 1),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );
        $this->assertSame('25.00', $order->shipping_fee, '280.00 net does not clear a 300.00 net threshold');
        $this->assertSame('347.00', $order->grand_total);
    }

    #[Test]
    public function the_shipping_vat_treatment_is_configurable_and_separate(): void
    {
        $product = $this->product(stock: 10, exclVat: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: null);

        // Exempt: delivery contributes no VAT, and charges the same.
        Setting::put('shipping_vat_mode', Vat::SHIPPING_EXEMPT);
        $order = app(CheckoutService::class)->place(
            $this->startCart($product, 1),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );
        $this->assertSame('25.00', $order->shipping_fee);
        $this->assertSame('0.00', $order->shipping_vat_amount);
        $this->assertSame('15.00', $order->vat_amount, 'only the goods carry VAT');
        $this->assertSame('140.00', $order->grand_total, 'the payable total is unchanged');
        $this->assertTrue($order->totalsReconcile());

        // Exclusive: the stored fee is net, so VAT on it is a genuine addition.
        // This is the one mode that moves what the customer pays.
        Setting::put('shipping_vat_mode', Vat::SHIPPING_EXCLUSIVE);
        $order = app(CheckoutService::class)->place(
            $this->startCart($product, 1),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );
        $this->assertSame('28.75', $order->shipping_fee, '25.00 net plus 3.75 VAT');
        $this->assertSame('3.75', $order->shipping_vat_amount);
        $this->assertSame('18.75', $order->vat_amount);
        $this->assertSame('143.75', $order->grand_total);
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function the_vat_rate_setting_governs_a_new_order(): void
    {
        /*
         | Without this the dashboard's VAT field would be decorative: it is
         | stored, shown and edited, while every order keeps using the config
         | value — misstating the tax on every receipt after a rate change.
         |
         | What it governs has narrowed, deliberately. Product VAT is now the
         | difference between two prices the client supplied, so no rate can
         | move it; the rate governs the VAT on DELIVERY (where there is only
         | one figure to work from) and the rate snapshotted on the order.
         */
        Setting::put('vat_rate', 0.05);

        $product = $this->product(stock: 10, exclVat: '150.00', inclVat: '172.50');
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($product, 2);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $this->assertSame('0.050000', $order->vat_rate, 'the setting is what gets snapshotted');

        // 25.00 inclusive at 5%: 25 x 0.05 / 1.05 = 1.1904…, rounded to 1.19.
        // At 15% it would be 3.26.
        $this->assertSame('1.19', $order->shipping_vat_amount);

        // Unmoved by the rate change: 172.50 - 150.00, twice.
        $this->assertSame('45.00', $order->product_vat_amount);
        $this->assertSame('46.19', $order->vat_amount);
        $this->assertSame('370.00', $order->grand_total);
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function product_vat_comes_from_the_two_prices_not_from_the_rate(): void
    {
        /*
         | Both brochure prices are authoritative, so where they disagree with
         | the configured rate it is the PRICES that decide the VAT — the
         | customer is charged the inclusive price and shown the exclusive one,
         | and the difference between them is the tax, whatever a rate would
         | have predicted.
         |
         | Recomputing it from the rate instead would make the stated VAT
         | inconsistent with the two figures on screen, which is precisely the
         | defect a customer notices on an invoice.
         */
        $product = $this->product(stock: 10, exclVat: '100.00', inclVat: '120.00');
        $zone = $this->zone(fee: '25.00', threshold: null);

        $order = app(CheckoutService::class)->place(
            $this->startCart($product, 2),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );

        $this->assertSame('200.00', $order->subtotal_excl_vat);
        $this->assertSame('240.00', $order->subtotal);
        // 40.00 — the price difference. At 15% on 200.00 it would be 30.00.
        $this->assertSame('40.00', $order->product_vat_amount);
        $this->assertSame('265.00', $order->grand_total);
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function multi_quantity_lines_keep_the_breakdown_reconciled(): void
    {
        // A net price whose VAT lands on a third decimal per unit: 33.33 x 0.15
        // = 4.9995. Multiplying the ROUNDED unit prices by the quantity is what
        // keeps the line, the order and the customer's arithmetic agreeing.
        $product = $this->product(stock: 20, exclVat: '33.33');
        $zone = $this->zone(fee: '25.00', threshold: null);

        $order = app(CheckoutService::class)->place(
            $this->startCart($product, 7),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );

        $item = $order->items->firstOrFail();

        $this->assertSame('38.33', $item->unit_price, '33.33 x 1.15 = 38.3295, rounded half-up');
        $this->assertSame('233.31', $item->line_total_excl_vat, '33.33 x 7');
        $this->assertSame('268.31', $item->line_total, '38.33 x 7');
        $this->assertSame('35.00', $item->vat_amount);

        $this->assertSame('233.31', $order->subtotal_excl_vat);
        $this->assertSame('268.31', $order->subtotal);
        $this->assertSame('293.31', $order->grand_total);
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function placing_an_order_consumes_the_cart_without_giving_stock_back(): void
    {
        $product = $this->product(stock: 10);
        $zone = $this->zone();
        $cart = $this->startCart($product, 3);

        // Reserved at add-to-cart.
        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        // Still 7: the units were sold, not returned.
        $this->assertSame(7, (int) $product->fresh()->stock_qty);
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertSame(0, CartItem::query()->count());

        $this->assertSame(
            1,
            ProductStockMovement::query()
                ->where('product_id', $product->id)
                ->where('reason', StockMovementReason::OrderFulfilled)
                ->count()
        );

        // The ledger still reconciles against the cached quantity.
        $sum = (int) ProductStockMovement::query()->where('product_id', $product->id)->sum('delta');
        $this->assertSame((int) $product->fresh()->stock_qty, $sum);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Pending->value,
        ]);
    }

    #[Test]
    public function a_second_order_reuses_the_customer_record(): void
    {
        $zone = $this->zone();

        foreach ([1, 2] as $_) {
            $product = $this->product(stock: 5);
            $cart = $this->startCart($product, 1);
            app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));
        }

        $this->assertSame(2, Order::query()->count());
        $this->assertSame(1, Customer::query()->count(), 'the same email must not create a second customer');
        $this->assertSame(2, (int) Customer::query()->firstOrFail()->orders()->count());
    }

    /* ── refusals ─────────────────────────────────────────── */

    #[Test]
    public function an_empty_cart_cannot_be_checked_out(): void
    {
        $zone = $this->zone();
        $cart = Cart::factory()->create();

        $this->expectException(CheckoutException::class);
        app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));
    }

    #[Test]
    public function a_product_deactivated_after_being_added_stops_the_order(): void
    {
        $product = $this->product(stock: 10);
        $zone = $this->zone();
        $cart = $this->startCart($product, 2);

        $product->update(['is_active' => false]);

        try {
            app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));
            $this->fail('expected CheckoutException');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString('no longer available', $e->getMessage());
        }

        // The whole placement is one transaction: nothing may survive it.
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
    }

    #[Test]
    public function an_order_is_repriced_from_the_live_product_not_the_cart_snapshot(): void
    {
        $product = $this->product(stock: 10, exclVat: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($product, 1);

        // BOTH prices move after the item was added.
        $product->update(['price_excl_vat' => '120.00', 'price_incl_vat' => '138.00']);

        $order = app(CheckoutService::class)->place($cart->fresh(), $this->details($zone), app(CashOnDeliveryGateway::class));

        $item = $order->items->firstOrFail();
        $this->assertSame('138.00', $item->unit_price, 'the live inclusive price is what is charged');
        $this->assertSame('120.00', $item->unit_price_excl_vat, 'and the live exclusive price is what is recorded');
        $this->assertSame('120.00', $order->subtotal_excl_vat);
        $this->assertSame('138.00', $order->subtotal);
    }

    #[Test]
    public function checkout_ignores_prices_submitted_by_the_browser(): void
    {
        /*
         | The place-order request carries an address, a payment method and an
         | idempotency token — and nothing that touches money. Posting price
         | fields alongside them must change nothing, because the amounts are
         | re-read from the locked product rows inside the transaction.
         */
        $product = $this->product(stock: 10, exclVat: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: null);
        $city = ShippingCity::query()->where('zone_id', $zone->id)->firstOrFail();
        $this->startCart($product, 1);

        // Every money field a hostile client could think to send, alongside the
        // legitimate address fields.
        $this->cartForm('POST', '/checkout/shipping', [
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => 'sara@example.com',
            'phone' => '0512345678',
            'zone_id' => $zone->id,
            'city_id' => $city->id,
            'address_line1' => 'King Fahd Road',
            'subtotal' => '1.00',
            'price' => '1.00',
            'price_excl_vat' => '1.00',
            'price_incl_vat' => '1.00',
            'grand_total' => '1.00',
        ])->assertRedirect('/checkout/payment');

        // The payment step issues the idempotency token.
        $this->cartGet('/checkout/payment')->assertOk();
        $token = session('checkout.token');
        $this->assertIsString($token);

        $this->cartForm('POST', '/checkout/payment', [
            'payment_method' => 'cod',
            'terms' => '1',
            'checkout_token' => $token,
            'subtotal' => '1.00',
            'subtotal_excl_vat' => '1.00',
            'grand_total' => '1.00',
            'shipping_fee' => '0.00',
            'vat_amount' => '0.00',
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame('100.00', $order->subtotal_excl_vat);
        $this->assertSame('115.00', $order->subtotal);
        $this->assertSame('25.00', $order->shipping_fee, 'the submitted 0.00 shipping was ignored');
        $this->assertSame('18.26', $order->vat_amount);
        $this->assertSame('140.00', $order->grand_total, 'the submitted 1.00 was ignored entirely');
    }

    #[Test]
    public function the_review_step_warns_when_a_price_has_moved(): void
    {
        $product = $this->product(stock: 10, exclVat: '100.00');
        $cart = $this->startCart($product, 1);

        $product->update(['price_excl_vat' => '130.00', 'price_incl_vat' => '149.50']);

        $review = app(CheckoutService::class)->review($cart->fresh());

        $this->assertTrue($review->hasWarnings());
        $line = $review->changedLines()[0];
        $this->assertTrue($line->priceWentUp());
        $this->assertSame('115.00', $line->previousPrice);
        $this->assertSame('149.50', $line->unitPrice);
        $this->assertSame('100.00', $line->previousPriceExclVat);
        $this->assertSame('130.00', $line->unitPriceExclVat);
    }

    #[Test]
    public function the_review_step_warns_when_only_one_of_the_two_prices_has_moved(): void
    {
        /*
         | Either price moving on its own is a change the shopper must be told
         | about. An admin who corrects only the VAT-exclusive figure changes
         | every number on the cart page while the inclusive snapshot still
         | matches — and a subtotal that moves with no explanation is exactly
         | what the change notice exists to prevent.
         */
        $exclusiveOnly = $this->product(stock: 10, exclVat: '100.00');
        $cart = $this->startCart($exclusiveOnly, 1);
        $exclusiveOnly->update(['price_excl_vat' => '110.00']);

        $line = app(CheckoutService::class)->review($cart->fresh())->changedLines()[0] ?? null;
        $this->assertNotNull($line, 'a move in the exclusive price alone must be reported');
        $this->assertTrue($line->item->exclusivePriceHasChanged());
        $this->assertFalse($line->item->inclusivePriceHasChanged());
        $this->assertTrue($line->priceWentUp());

        // A SECOND cart, not a second line on the first one: startCart() reuses
        // whatever cookie is held, and adding to the existing cart would leave
        // changedLines()[0] pointing at the product above.
        $this->actingCart = null;

        $inclusiveOnly = $this->product(stock: 10, exclVat: '100.00');
        $secondCart = $this->startCart($inclusiveOnly, 1);
        $inclusiveOnly->update(['price_incl_vat' => '112.00']);

        $line = app(CheckoutService::class)->review($secondCart->fresh())->changedLines()[0] ?? null;
        $this->assertNotNull($line, 'a move in the inclusive price alone must be reported');
        $this->assertFalse($line->item->exclusivePriceHasChanged());
        $this->assertTrue($line->item->inclusivePriceHasChanged());
        $this->assertFalse($line->priceWentUp(), '112.00 is below the 115.00 that was snapshotted');
    }

    /* ── HTTP guards ──────────────────────────────────────── */

    #[Test]
    public function checkout_redirects_to_the_cart_when_there_is_nothing_to_buy(): void
    {
        $this->get('/checkout')->assertRedirect('/checkout/review');
        $this->get('/checkout/review')->assertRedirect('/cart');
        $this->get('/checkout/shipping')->assertRedirect('/cart');
    }

    #[Test]
    public function the_payment_step_cannot_be_reached_without_shipping_details(): void
    {
        $product = $this->product();
        $this->startCart($product, 1);

        $this->cartGet('/checkout/payment')->assertRedirect('/checkout/shipping');
    }

    #[Test]
    public function a_non_saudi_mobile_is_rejected(): void
    {
        $product = $this->product();
        $zone = $this->zone();
        $city = ShippingCity::query()->where('zone_id', $zone->id)->firstOrFail();
        $this->startCart($product, 1);

        $this->cartForm('POST', '/checkout/shipping', [
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => 'sara@example.com',
            'phone' => '0112345678', // landline
            'zone_id' => $zone->id,
            'city_id' => $city->id,
            'address_line1' => 'King Fahd Road',
        ])->assertSessionHasErrors('phone');
    }

    #[Test]
    public function a_city_from_another_zone_is_rejected(): void
    {
        // Otherwise a crafted request could pay a Riyadh rate for a far city.
        $product = $this->product();
        $zoneA = $this->zone();
        $zoneB = $this->zone(fee: '40.00');
        $cityB = ShippingCity::query()->where('zone_id', $zoneB->id)->firstOrFail();
        $this->startCart($product, 1);

        $this->cartForm('POST', '/checkout/shipping', [
            'first_name' => 'Sara',
            'last_name' => 'Al Harbi',
            'email' => 'sara@example.com',
            'phone' => '0512345678',
            'zone_id' => $zoneA->id,
            'city_id' => $cityB->id,
            'address_line1' => 'King Fahd Road',
        ])->assertSessionHasErrors('city_id');
    }

    #[Test]
    public function the_confirmation_is_only_readable_by_the_session_that_placed_it(): void
    {
        $product = $this->product();
        $zone = $this->zone();
        $cart = $this->startCart($product, 1);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        // A session that never placed it cannot read it, even knowing the number.
        $this->get("/checkout/confirmation/{$order->order_no}")->assertNotFound();

        // The placing session can.
        $this->withSession(['checkout.orders' => [$order->order_no]])
            ->get("/checkout/confirmation/{$order->order_no}")
            ->assertOk()
            ->assertSee($order->order_no);
    }

    #[Test]
    public function resubmitting_a_spent_token_returns_the_order_rather_than_an_empty_cart(): void
    {
        $product = $this->product();
        $zone = $this->zone();
        $cart = $this->startCart($product, 1);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        // The cart is gone by now, which is exactly the state a double-click
        // lands in. The shopper must be shown their order, not an empty cart.
        $response = $this->withSession(['checkout.orders' => [$order->order_no]])
            ->post('/checkout/payment', [
                'payment_method' => 'cod',
                'terms' => '1',
                'checkout_token' => 'a-stale-token',
            ]);

        $response->assertRedirect("/checkout/confirmation/{$order->order_no}");
        $this->assertSame(1, Order::query()->count(), 'no second order may be created');
    }
}
