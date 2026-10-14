<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\OrderStatusService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Support\Vat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * With kotiva.commerce_enabled off, Salla owns checkout and orders, and the
 * Laravel services that used to own them must refuse to write anything.
 *
 * The routes are already redirected or answer 410 (SallaHandoffTest); these
 * tests pin the layer underneath, so a forgotten controller, job or tinker
 * session cannot place an order, move one along or mark it paid.
 *
 * Fixtures are written straight to the database rather than through
 * StockService or the cart endpoints, because both of those are themselves
 * switched off in this mode.
 */
final class CommerceDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['kotiva.commerce_enabled' => false]);
    }

    private function product(int $stock = 10, string $exclVat = '150.00'): Product
    {
        return Product::factory()->withStock($stock)->create([
            'price_excl_vat' => $exclVat,
            'price_incl_vat' => Vat::inclusiveOf($exclVat),
        ]);
    }

    private function zone(): ShippingZone
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create([
            'fee' => '25.00',
            'free_shipping_threshold' => '300.00',
        ]);
        ShippingCity::factory()->for($zone, 'zone')->create(['name_en' => 'Riyadh']);

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
            'marketing_opt_in' => true,
        ], $city->displayName());
    }

    private function cartWith(Product $product, int $qty = 2): Cart
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->snapshotOf($product)->create(['qty' => $qty]);

        return $cart->fresh();
    }

    /* ── checkout ────────────────────────────────────────────── */

    #[Test]
    public function placing_an_order_is_refused_and_writes_nothing(): void
    {
        Event::fake([OrderPlaced::class]);

        $product = $this->product(10);
        $cart = $this->cartWith($product);

        try {
            app(CheckoutService::class)->place(
                $cart,
                $this->details($this->zone()),
                app(CashOnDeliveryGateway::class),
            );
            $this->fail('place() must refuse while commerce is disabled.');
        } catch (LogicException $e) {
            $this->assertSame('Laravel commerce is disabled; checkout is owned by Salla.', $e->getMessage());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, OrderStatusHistory::query()->count());
        $this->assertSame(0, ProductStockMovement::query()->count());
        $this->assertSame(10, $product->fresh()->stock_qty);

        // The cart is left exactly as it was: nothing was consumed.
        $this->assertTrue(Cart::query()->whereKey($cart->id)->exists());
        $this->assertSame(1, $cart->items()->count());

        Event::assertNotDispatched(OrderPlaced::class);
    }

    /* ── order status ────────────────────────────────────────── */

    #[Test]
    public function a_status_transition_is_refused_and_the_order_is_unchanged(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $product = $this->product(7);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['qty' => 3]);
        $before = $order->fresh()->only(['status', 'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'updated_at']);

        // Cancelling is the dangerous one: it would put units back into a
        // stock figure Salla now owns.
        foreach ([OrderStatus::Confirmed, OrderStatus::Cancelled] as $to) {
            try {
                app(OrderStatusService::class)->transition($order, $to, 'from a stale dashboard tab');
                $this->fail('transition() must refuse while commerce is disabled.');
            } catch (LogicException $e) {
                $this->assertSame('Laravel commerce is disabled; orders are owned by Salla.', $e->getMessage());
            }
        }

        $this->assertEquals($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame(0, OrderStatusHistory::query()->count());
        $this->assertSame(0, ProductStockMovement::query()->count());
        $this->assertSame(7, $product->fresh()->stock_qty);

        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    #[Test]
    public function marking_an_order_paid_is_refused_and_payment_is_unchanged(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $before = $order->fresh()->only(['status', 'payment_status', 'updated_at']);

        try {
            app(OrderStatusService::class)->markPaid($order, null, 'Cash collected');
            $this->fail('markPaid() must refuse while commerce is disabled.');
        } catch (LogicException $e) {
            $this->assertSame('Laravel commerce is disabled; orders are owned by Salla.', $e->getMessage());
        }

        $this->assertEquals($before, $order->fresh()->only(array_keys($before)));
        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertSame(0, OrderStatusHistory::query()->count());

        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    #[Test]
    public function the_read_only_helpers_still_answer(): void
    {
        // The dashboard can still describe a legacy order; only changing it
        // is refused.
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->create(['qty' => 4]);

        $service = app(OrderStatusService::class);

        $this->assertSame([OrderStatus::Confirmed, OrderStatus::Cancelled], $service->availableTransitions($order));
        $this->assertSame(4, $service->wouldRestoreUnits($order));
    }
}
