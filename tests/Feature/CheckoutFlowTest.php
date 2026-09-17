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
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\StockService;
use App\Support\OrderNumber;
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

    private function product(int $stock = 10, string $price = '150.00'): Product
    {
        $product = Product::factory()->withStock(0)->create(['price' => $price]);
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
        $product = $this->product(stock: 10, price: '150.00');
        $zone = $this->zone(fee: '25.00', threshold: '300.00');
        $cart = $this->startCart($product, 2);

        $order = app(CheckoutService::class)->place(
            cart: $cart,
            details: $this->details($zone),
            gateway: app(CashOnDeliveryGateway::class),
        );

        $this->assertTrue(OrderNumber::isValid($order->order_no));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status, 'COD is not paid at checkout');

        // 2 x 150 = 300, which hits the free-shipping threshold exactly.
        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertSame('300.00', $order->grand_total);

        // VAT is EXTRACTED from the inclusive total, never added to it.
        $this->assertSame('39.13', $order->vat_amount);

        $item = $order->items->firstOrFail();
        $this->assertSame($product->sku, $item->sku_snapshot);
        $this->assertSame($product->name, $item->name_snapshot);
        $this->assertSame('150.00', $item->unit_price);
        $this->assertSame(2, $item->qty);
        $this->assertSame('300.00', $item->line_total);
    }

    #[Test]
    public function the_shipping_fee_applies_below_the_free_threshold(): void
    {
        $product = $this->product(stock: 10, price: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: '300.00');
        $cart = $this->startCart($product, 1);

        $order = app(CheckoutService::class)->place($cart, $this->details($zone), app(CashOnDeliveryGateway::class));

        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('25.00', $order->shipping_fee);
        $this->assertSame('125.00', $order->grand_total);
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
        $product = $this->product(stock: 10, price: '100.00');
        $zone = $this->zone(fee: '25.00', threshold: null);
        $cart = $this->startCart($product, 1);

        // Price moves after the item was added.
        $product->update(['price' => '120.00']);

        $order = app(CheckoutService::class)->place($cart->fresh(), $this->details($zone), app(CashOnDeliveryGateway::class));

        $this->assertSame('120.00', $order->items->firstOrFail()->unit_price, 'the live price is what is charged');
        $this->assertSame('120.00', $order->subtotal);
    }

    #[Test]
    public function the_review_step_warns_when_a_price_has_moved(): void
    {
        $product = $this->product(stock: 10, price: '100.00');
        $cart = $this->startCart($product, 1);

        $product->update(['price' => '130.00']);

        $review = app(CheckoutService::class)->review($cart->fresh());

        $this->assertTrue($review->hasWarnings());
        $line = $review->changedLines()[0];
        $this->assertTrue($line->priceWentUp());
        $this->assertSame('100.00', $line->previousPrice);
        $this->assertSame('130.00', $line->unitPrice);
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
