<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementReason;
use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An order is a RECORD, not a view over current data.
 *
 * That was already true of this project; dual pricing adds four more figures
 * per order and two more per line, and every one of them has to be as immutable
 * as the ones that came before. The tests here are the ones that would catch a
 * snapshot quietly turning back into a join.
 */
final class DualPriceOrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private ?Cart $actingCart = null;

    #[Test]
    public function a_placed_order_is_untouched_by_later_price_changes(): void
    {
        $product = $this->product('135.00', '155.25');
        $zone = $this->zone('25.00', null);

        $order = app(CheckoutService::class)->place(
            $this->startCart($product, 2),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );

        $before = $order->only([
            'subtotal', 'subtotal_excl_vat', 'shipping_fee', 'vat_amount',
            'product_vat_amount', 'shipping_vat_amount', 'vat_rate', 'grand_total',
        ]);
        $itemBefore = $order->items->firstOrFail()->only([
            'unit_price', 'unit_price_excl_vat', 'line_total', 'line_total_excl_vat', 'vat_amount', 'vat_rate',
        ]);

        // Everything the catalog and the business rules could possibly do next.
        $product->update(['price_excl_vat' => '999.00', 'price_incl_vat' => '1148.85']);
        Setting::put('vat_rate', 0.25);
        Setting::put('shipping_vat_mode', 'exempt');
        ShippingRate::query()->update(['fee' => '999.00']);

        $order->refresh()->load('items');

        $this->assertSame($before, $order->only(array_keys($before)));
        $this->assertSame($itemBefore, $order->items->firstOrFail()->only(array_keys($itemBefore)));
        $this->assertTrue($order->totalsReconcile());
    }

    #[Test]
    public function a_reprinted_receipt_uses_the_rate_the_order_was_placed_at(): void
    {
        // The rate is snapshotted precisely so a receipt reprinted after a rate
        // change still describes the transaction that happened, rather than
        // relabelling an old order with today's percentage.
        $order = app(CheckoutService::class)->place(
            $this->startCart($this->product('135.00', '155.25'), 1),
            $this->details($this->zone('25.00', null)),
            app(CashOnDeliveryGateway::class),
        );

        $this->assertSame('15%', $order->vatRateLabel());

        Setting::put('vat_rate', 0.25);

        $this->assertSame('15%', $order->fresh()->vatRateLabel());
        $this->assertSame('15%', $order->fresh()->items->firstOrFail()->vatRateLabel());

        $slip = view('pdf.packing-slip', ['order' => $order->fresh(['items', 'customer'])])->render();
        $this->assertStringContainsString('VAT at 15%', $slip);
        $this->assertStringNotContainsString('VAT at 25%', $slip);
    }

    #[Test]
    public function every_line_reconciles_against_the_order_total(): void
    {
        // Three different prices and quantities, including one whose per-unit
        // VAT does not land on a whole halala.
        $zone = $this->zone('25.00', null);

        $this->startCart($this->product('33.33', '38.33'), 3);
        $this->addToCart($this->product('135.00', '155.25'), 2);
        $this->addToCart($this->product('80.00', '92.00'), 5);

        $order = app(CheckoutService::class)->place(
            $this->actingCart->fresh(),
            $this->details($zone),
            app(CashOnDeliveryGateway::class),
        );

        $order->load('items');

        $this->assertCount(3, $order->items);

        $lineExcl = Money::sum($order->items->map(fn ($i): string => Money::of($i->line_total_excl_vat)));
        $lineIncl = Money::sum($order->items->map(fn ($i): string => Money::of($i->line_total)));
        $lineVat = Money::sum($order->items->map(fn ($i): string => Money::of($i->vat_amount)));

        $this->assertSame($lineExcl, Money::of($order->subtotal_excl_vat), 'lines must sum to the net subtotal');
        $this->assertSame($lineIncl, Money::of($order->subtotal), 'lines must sum to the gross subtotal');
        $this->assertSame($lineVat, Money::of($order->product_vat_amount), 'lines must sum to the product VAT');
        $this->assertTrue($order->totalsReconcile());

        // And each line stands up on its own.
        foreach ($order->items as $item) {
            $this->assertSame(
                Money::of($item->line_total),
                Money::add((string) $item->line_total_excl_vat, (string) $item->vat_amount),
                $item->sku_snapshot.': net + VAT must equal gross'
            );
            $this->assertSame(
                Money::of($item->line_total),
                Money::multiplyByQty((string) $item->unit_price, $item->qty),
                $item->sku_snapshot.': line total must be unit price x quantity'
            );
        }
    }

    #[Test]
    public function a_failed_placement_writes_no_partial_breakdown(): void
    {
        // The whole placement is still one transaction. Adding six money
        // columns must not have created a window where half of them exist.
        $product = $this->product('135.00', '155.25');
        $zone = $this->zone('25.00', null);
        $cart = $this->startCart($product, 2);

        // Deactivated after the cart was built: placement must refuse.
        $product->update(['is_active' => false]);

        try {
            app(CheckoutService::class)->place($cart->fresh(), $this->details($zone), app(CashOnDeliveryGateway::class));
            $this->fail('placement should have been refused');
        } catch (CheckoutException) {
            // expected
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
    }

    #[Test]
    public function a_resubmitted_order_is_not_placed_twice_with_a_second_set_of_totals(): void
    {
        // Idempotency is unchanged, but it is worth re-pinning now that a
        // duplicate would also duplicate the VAT figures in any report.
        $order = app(CheckoutService::class)->place(
            $this->startCart($this->product('135.00', '155.25'), 1),
            $this->details($this->zone('25.00', null)),
            app(CashOnDeliveryGateway::class),
        );

        $this->withSession(['checkout.orders' => [$order->order_no]])
            ->post('/checkout/payment', [
                'payment_method' => 'cod',
                'terms' => '1',
                'checkout_token' => 'a-stale-token',
            ])
            ->assertRedirect('/checkout/confirmation/'.$order->order_no);

        $this->assertSame(1, Order::query()->count());
        $this->assertSame('180.25', Money::of(Order::query()->sum('grand_total')), '155.25 + 25.00, once');
    }

    #[Test]
    public function the_admin_order_view_reports_a_breakdown_that_does_not_reconcile(): void
    {
        /*
         | A stored breakdown that no longer adds up is worth surfacing where
         | somebody will see it rather than leaving for a customer to find.
         | Forced here by writing a bad row directly, which is the only way it
         | could happen — nothing in the application produces one.
         */
        $order = app(CheckoutService::class)->place(
            $this->startCart($this->product('135.00', '155.25'), 1),
            $this->details($this->zone('25.00', null)),
            app(CashOnDeliveryGateway::class),
        );

        $this->assertTrue($order->totalsReconcile());

        $order->forceFill(['product_vat_amount' => '1.00'])->save();

        $this->assertFalse($order->fresh()->totalsReconcile());
    }

    /* ── fixtures ─────────────────────────────────────────── */

    private function product(string $excl, string $incl): Product
    {
        $product = Product::factory()->create([
            'price_excl_vat' => $excl,
            'price_incl_vat' => $incl,
            'stock_qty' => 0,
        ]);

        app(StockService::class)->adjust($product, 50, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    private function zone(string $fee, ?string $threshold): ShippingZone
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
        ], $city->displayName());
    }

    private function startCart(Product $product, int $qty): Cart
    {
        $this->addToCart($product, $qty);

        return $this->actingCart;
    }

    private function addToCart(Product $product, int $qty): void
    {
        $this->cartJson('POST', '/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();

        $this->actingCart = Cart::query()->latest('id')->firstOrFail();
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
}
