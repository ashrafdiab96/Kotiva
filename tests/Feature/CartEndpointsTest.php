<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementReason;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Services\StockService;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The cart HTTP surface, including the guard that stops one visitor touching
 * another's cart. That guard is a security control, not a convenience: cart
 * lines hold stock reservations, so being able to delete a stranger's line
 * would let anyone release stock they never reserved.
 *
 * ── Cookie handling ──────────────────────────────────────────────────────
 * The cart is identified solely by an httpOnly cookie, so a multi-step test
 * has to present that cookie or every request looks like a new visitor.
 *
 * withCookie() does not do it here — a probe route showed the request
 * arriving with no cookies at all — so cookies are passed to call()
 * explicitly, encrypted and prefixed exactly as EncryptCookies expects.
 *
 * This is worth the ceremony because the failure is silent and inverted: with
 * no cookie the ownership tests below are refused by the "visitor has no
 * cart" branch, never reach the ownership comparison, and pass while testing
 * nothing. They only became real tests once the cookie was genuinely carried.
 */
final class CartEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** The cart subsequent requests belong to, or null for a fresh visitor. */
    private ?Cart $actingCart = null;

    private function product(int $stock = 10): Product
    {
        $product = Product::factory()->withStock(0)->create();
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    private function asCart(?Cart $cart): void
    {
        $this->actingCart = $cart;
    }

    /**
     * The cart cookie as the framework would have set it: encrypted, carrying
     * the CookieValuePrefix that EncryptCookies validates on the way in.
     *
     * @return array<string, string>
     */
    private function cartCookies(): array
    {
        if (! $this->actingCart instanceof Cart) {
            return [];
        }

        $name = (string) config('kotiva.cart.cookie');

        return [
            $name => encrypt(
                CookieValuePrefix::create($name, app('encrypter')->getKey()).$this->actingCart->token,
                false
            ),
        ];
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

    /**
     * Adds through the real endpoint, then keeps the resulting cart so the rest
     * of the test behaves like one continuous visitor.
     */
    private function startCart(Product $product, int $qty = 1): Cart
    {
        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();

        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->asCart($cart);

        return $cart;
    }

    private function assertLedgerReconciles(Product $product): void
    {
        $sum = (int) ProductStockMovement::query()->where('product_id', $product->getKey())->sum('delta');

        $this->assertSame((int) $product->fresh()->stock_qty, $sum);
    }

    /*
     | These two assert on markup unique to the cart page, not on its wording.
     | "Your cart is empty" also appears in the mini-cart drawer, which the
     | shared layout renders on EVERY page — so asserting the sentence made the
     | empty-state test pass for a full cart and the populated test impossible
     | to pass. Matching .cart-empty / .cart-item keeps each test about the
     | thing it names.
     */

    #[Test]
    public function the_cart_page_renders_an_empty_state(): void
    {
        $this->cartGet('/cart')
            ->assertOk()
            ->assertSee('class="cart-empty"', false)
            ->assertSee('Discover the Range')
            ->assertDontSee('class="cart-item"', false);
    }

    #[Test]
    public function the_summary_is_empty_before_anything_is_added(): void
    {
        $this->cartJson('GET', '/cart/summary')
            ->assertOk()
            ->assertJson(['count' => 0, 'subtotal' => '0.00']);
    }

    #[Test]
    public function merely_browsing_does_not_create_a_cart(): void
    {
        $this->get('/');
        $this->get('/shop');
        $this->cartGet('/cart');

        $this->assertSame(0, Cart::query()->count(), 'a cart row should only exist once something is added');
    }

    #[Test]
    public function adding_a_product_reserves_its_stock(): void
    {
        $product = $this->product(10);

        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 2])
            ->assertOk()
            ->assertJson(['count' => 2]);

        $this->assertSame(8, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function adding_the_same_product_again_increases_the_existing_line(): void
    {
        $product = $this->product(10);

        $this->startCart($product, 2);
        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 3])->assertOk();

        $this->assertSame(1, Cart::query()->count(), 'the cookie should keep this one visitor');
        $this->assertSame(1, CartItem::query()->count(), 'one line per product');
        $this->assertSame(5, (int) CartItem::query()->firstOrFail()->qty);
        $this->assertSame(5, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function adding_more_than_is_in_stock_is_refused_with_the_maximum(): void
    {
        $product = $this->product(2);

        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 5])
            ->assertStatus(422)
            ->assertJson(['available' => 2, 'product_id' => $product->id]);

        // Nothing reserved, nothing created.
        $this->assertSame(2, (int) $product->fresh()->stock_qty);
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function updating_a_quantity_moves_only_the_difference(): void
    {
        $product = $this->product(10);
        $this->startCart($product, 2);
        $item = CartItem::query()->firstOrFail();

        $this->cartJson('PATCH', "/cart/items/{$item->id}", ['qty' => 5])
            ->assertOk()
            ->assertJson(['count' => 5]);

        $this->assertSame(5, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function updating_to_zero_removes_the_line_and_releases_stock(): void
    {
        $product = $this->product(10);
        $this->startCart($product, 3);
        $item = CartItem::query()->firstOrFail();

        $this->cartJson('PATCH', "/cart/items/{$item->id}", ['qty' => 0])->assertOk();

        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(10, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function removing_a_line_releases_its_stock(): void
    {
        $product = $this->product(10);
        $this->startCart($product, 4);
        $item = CartItem::query()->firstOrFail();

        $this->cartJson('DELETE', "/cart/items/{$item->id}")->assertOk();

        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(10, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    /*
     | Ownership. Each of these gives the visitor a real, live cart of their own
     | first, so the request reaches the ownership comparison rather than being
     | turned away for having no cart.
     */

    #[Test]
    public function a_visitor_cannot_update_another_visitors_cart_line(): void
    {
        $product = $this->product(20);

        $otherCart = Cart::factory()->create();
        $otherItem = CartItem::factory()->for($otherCart)->for($product)->create(['qty' => 2]);

        $mine = $this->startCart($product, 1);
        $this->assertNotSame($mine->id, $otherCart->id);

        $this->cartJson('PATCH', "/cart/items/{$otherItem->id}", ['qty' => 9])->assertNotFound();

        $this->assertSame(2, (int) $otherItem->fresh()->qty, 'the other cart must be untouched');

        // The visitor's own cart still works, proving the refusal was about
        // ownership rather than a missing or unrecognised cookie.
        $ownItem = CartItem::query()->where('cart_id', $mine->id)->firstOrFail();
        $this->cartJson('PATCH', "/cart/items/{$ownItem->id}", ['qty' => 2])->assertOk();
    }

    #[Test]
    public function a_visitor_cannot_delete_another_visitors_cart_line(): void
    {
        $product = $this->product(20);

        $otherCart = Cart::factory()->create();
        $otherItem = CartItem::factory()->for($otherCart)->for($product)->create(['qty' => 2]);

        $this->startCart($product, 1);

        $this->cartJson('DELETE', "/cart/items/{$otherItem->id}")->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
    }

    #[Test]
    public function deleting_another_visitors_line_cannot_release_their_stock(): void
    {
        // The reason ownership matters: a released reservation is real stock.
        $product = $this->product(10);

        $otherCart = Cart::factory()->create();
        $otherItem = CartItem::factory()->for($otherCart)->for($product)->create(['qty' => 3]);
        app(StockService::class)->reserve($product, 3, $otherItem);

        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        $this->startCart($product, 1);
        $this->cartJson('DELETE', "/cart/items/{$otherItem->id}")->assertNotFound();

        // 10 - 3 (theirs) - 1 (mine) = 6. Had the guard failed it would be 9.
        $this->assertSame(6, (int) $product->fresh()->stock_qty);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function a_visitor_with_no_cart_at_all_cannot_touch_a_line(): void
    {
        $product = $this->product(10);
        $otherItem = CartItem::factory()->for(Cart::factory())->for($product)->create(['qty' => 2]);

        $this->cartJson('DELETE', "/cart/items/{$otherItem->id}")->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
    }

    #[Test]
    public function an_inactive_product_cannot_be_added(): void
    {
        $product = $this->product(10);
        $product->update(['is_active' => false]);

        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertNotFound();
    }

    #[Test]
    public function the_quantity_is_validated(): void
    {
        $product = $this->product(10);
        $max = (int) config('kotiva.cart.max_qty_per_line');

        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('qty');

        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => $max + 1])
            ->assertStatus(422)->assertJsonValidationErrors('qty');

        $this->cartJson('POST', '/cart/items', ['product_id' => 999999, 'qty' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('product_id');
    }

    #[Test]
    public function an_expired_cart_is_treated_as_empty_and_gives_its_stock_back(): void
    {
        $product = $this->product(10);
        $cart = $this->startCart($product, 3);

        $this->assertSame(7, (int) $product->fresh()->stock_qty);

        // Push it past its TTL.
        $cart->forceFill(['expires_at' => now()->subDay()])->save();

        $this->cartJson('GET', '/cart/summary')->assertOk()->assertJson(['count' => 0]);

        $this->assertSame(10, (int) $product->fresh()->stock_qty, 'expired carts must release their reservation');
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function the_purge_command_releases_stock_from_expired_carts(): void
    {
        $product = $this->product(10);
        $cart = $this->startCart($product, 4);
        $cart->forceFill(['expires_at' => now()->subDay()])->save();

        $this->assertSame(6, (int) $product->fresh()->stock_qty);

        $this->artisan('carts:purge')->assertSuccessful();

        $this->assertSame(10, (int) $product->fresh()->stock_qty);
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertLedgerReconciles($product);
    }

    #[Test]
    public function a_live_cart_is_left_alone_by_the_purge(): void
    {
        $product = $this->product(10);
        $this->startCart($product, 2);

        $this->artisan('carts:purge')->assertSuccessful();

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(8, (int) $product->fresh()->stock_qty);
    }

    #[Test]
    public function a_non_json_request_redirects_back_so_it_works_without_javascript(): void
    {
        $product = $this->product(10);

        $this->from('/shop')
            ->post('/cart/items', ['product_id' => $product->id, 'qty' => 1])
            ->assertRedirect('/shop')
            ->assertSessionHas('cart_status');

        $this->assertSame(9, (int) $product->fresh()->stock_qty);
    }

    #[Test]
    public function the_summary_reports_server_computed_totals(): void
    {
        $product = $this->product(10);
        $product->update(['price' => '100.00']);

        $this->startCart($product, 3);

        $this->cartJson('GET', '/cart/summary')
            ->assertOk()
            ->assertJson([
                'count' => 3,
                'subtotal' => '300.00',
                'currency' => 'SAR',
            ]);
    }

    #[Test]
    public function the_cart_page_lists_what_was_added(): void
    {
        $product = $this->product(10);
        $this->startCart($product, 2);

        $this->cartGet('/cart')
            ->assertOk()
            ->assertSee('class="cart-item"', false)
            ->assertSee($product->displayName())
            ->assertDontSee('class="cart-empty"', false);
    }
}
