<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Mail\OrderPlacedAdmin;
use App\Mail\OrderPlacedCustomer;
use App\Mail\OrderStatusUpdated;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\OrderStatusService;
use App\Services\Payments\CashOnDeliveryGateway;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §6.8: who is emailed, and — just as importantly — who is not.
 *
 * An order that silently notifies nobody looks identical to a shop with no
 * orders, so these assert the dispatch itself rather than trusting that a
 * listener exists.
 */
final class OrderMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function product(int $stock = 10, string $price = '150.00'): Product
    {
        $product = Product::factory()->withStock(0)->create(['price' => $price]);
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    private function zone(): ShippingZone
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create();
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
            'marketing_opt_in' => false,
        ], $city->displayName());
    }

    private function startCart(Product $product, int $qty = 1): Cart
    {
        $name = (string) config('kotiva.cart.cookie');

        $this->call(
            method: 'POST',
            uri: '/cart/items',
            cookies: [],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: (string) json_encode(['product_id' => $product->id, 'qty' => $qty]),
        )->assertOk();

        $cart = Cart::query()->latest('id')->firstOrFail();

        // Carried so any follow-up request in the same test is the same visitor.
        $this->withCookie($name, $cart->token);

        return $cart;
    }

    private function placeOrder(): Order
    {
        $product = $this->product();
        $zone = $this->zone();
        $cart = $this->startCart($product, 1);

        return app(CheckoutService::class)->place(
            $cart,
            $this->details($zone),
            app(CashOnDeliveryGateway::class)
        );
    }

    #[Test]
    public function placing_an_order_emails_the_customer_and_the_shop(): void
    {
        $order = $this->placeOrder();

        Mail::assertQueued(
            OrderPlacedCustomer::class,
            fn (OrderPlacedCustomer $mail): bool => $mail->hasTo('sara@example.com')
                && $mail->order->is($order)
        );

        Mail::assertQueued(OrderPlacedAdmin::class);
    }

    #[Test]
    public function the_admin_notification_goes_to_the_configured_recipients(): void
    {
        Setting::put('admin_notification_emails', ['ops@kotiva.test', 'owner@kotiva.test']);

        $this->placeOrder();

        Mail::assertQueued(
            OrderPlacedAdmin::class,
            fn (OrderPlacedAdmin $mail): bool => $mail->hasTo('ops@kotiva.test')
                && $mail->hasTo('owner@kotiva.test')
        );
    }

    #[Test]
    public function a_malformed_recipient_setting_does_not_stop_the_customer_being_emailed(): void
    {
        // The customer's confirmation must never depend on the shop's own
        // notification settings being valid.
        Setting::put('admin_notification_emails', ['not-an-email', '']);

        $this->placeOrder();

        Mail::assertQueued(OrderPlacedCustomer::class);
        Mail::assertNotQueued(OrderPlacedAdmin::class);
    }

    #[Test]
    public function shipping_delivering_and_cancelling_tell_the_customer(): void
    {
        $service = app(OrderStatusService::class);

        foreach ([OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            Mail::fake();
            $order = Order::factory()->create(['status' => OrderStatus::Processing]);

            if ($status === OrderStatus::Delivered) {
                $order = $service->transition($order, OrderStatus::Shipped);
            }

            $service->transition($order, $status);

            Mail::assertQueued(
                OrderStatusUpdated::class,
                fn (OrderStatusUpdated $mail): bool => $mail->status === $status
            );
        }

        Mail::fake();
        $cancelled = Order::factory()->create(['status' => OrderStatus::Pending]);
        $service->transition($cancelled, OrderStatus::Cancelled);

        Mail::assertQueued(
            OrderStatusUpdated::class,
            fn (OrderStatusUpdated $mail): bool => $mail->status === OrderStatus::Cancelled
        );
    }

    #[Test]
    public function internal_status_steps_do_not_email_the_customer(): void
    {
        // Confirmed and Processing are shop-side bookkeeping. Emailing them
        // would train customers to ignore the messages that matter.
        $service = app(OrderStatusService::class);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $order = $service->transition($order, OrderStatus::Confirmed);
        $service->transition($order, OrderStatus::Processing);

        Mail::assertNotQueued(OrderStatusUpdated::class);
    }

    #[Test]
    public function the_order_emails_are_queued_rather_than_sent_inline(): void
    {
        // Placing an order must not wait on an SMTP handshake.
        $this->placeOrder();

        Mail::assertNothingSent();
        Mail::assertQueued(OrderPlacedCustomer::class);
    }
}
