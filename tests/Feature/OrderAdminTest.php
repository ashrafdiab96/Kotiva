<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementReason;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Mail\OrderPlacedCustomer;
use App\Mail\OrderStatusUpdated;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The orders dashboard (§7.4).
 *
 * An order is the one record in this system that must never be edited into a
 * new shape: its figures are a snapshot of what was agreed, and its status
 * carries side effects — stock returning to the catalog, a customer being
 * emailed, an audit row being written. So these tests check that the dashboard
 * can only move an order through the service, and that what the service
 * promises actually happens when the dashboard asks.
 */
final class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * An order with one real product line behind it, stocked through the
     * service so the ledger is consistent before the test starts.
     */
    private function order(OrderStatus $status = OrderStatus::Pending, int $stock = 10, int $qty = 2): Order
    {
        $customer = Customer::factory()->create(['phone' => '+966551234567']);
        $order = Order::factory()->for($customer)->status($status)->create();

        $product = Product::factory()->withStock(0)->create();
        app(StockService::class)->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        OrderItem::factory()->for($order)->for($product)->create([
            'qty' => $qty,
            'unit_price' => '150.00',
            'line_total' => bcmul('150.00', (string) $qty, 2),
        ]);

        return $order->fresh();
    }

    #[Test]
    public function the_list_and_view_pages_render(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        $this->assertSame(200, $this->get(OrderResource::getUrl('index'))->status(), 'list page');
        $this->assertSame(
            200,
            $this->get(OrderResource::getUrl('view', ['record' => $order]))->status(),
            'view page'
        );
    }

    #[Test]
    public function orders_cannot_be_created_or_edited_from_the_dashboard(): void
    {
        $this->actingAsAdmin();

        // Not merely hidden — the routes must not exist at all.
        $this->assertFalse(OrderResource::canCreate());
        $this->assertArrayNotHasKey('create', OrderResource::getPages());
        $this->assertArrayNotHasKey('edit', OrderResource::getPages());
    }

    #[Test]
    public function changing_status_writes_the_timeline_and_stamps_the_timestamp(): void
    {
        $admin = $this->actingAsAdmin();
        $order = $this->order(OrderStatus::Pending);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', [
                'status' => OrderStatus::Confirmed->value,
                'note' => 'Called the customer to confirm',
            ]);

        $order->refresh();

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at, 'confirmed_at must be stamped');

        $history = $order->statusHistories()->latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::Pending, $history->from_status);
        $this->assertSame(OrderStatus::Confirmed, $history->to_status);
        $this->assertSame('Called the customer to confirm', $history->note);
        $this->assertSame($admin->getKey(), $history->admin_id);

        // Confirmed is not a status the customer is told about.
        Mail::assertNotQueued(OrderStatusUpdated::class);
    }

    #[Test]
    public function an_illegal_transition_is_refused_and_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $order = $this->order(OrderStatus::Pending);

        // Pending cannot jump straight to Delivered. The dropdown would never
        // offer it, but the action must refuse it even when asked directly.
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', ['status' => OrderStatus::Delivered->value]);

        $order->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->delivered_at);
        $this->assertSame(0, $order->statusHistories()->count(), 'a refused transition must leave no audit row');
    }

    #[Test]
    public function cancelling_returns_the_units_to_stock_and_emails_the_customer(): void
    {
        $this->actingAsAdmin();
        $order = $this->order(OrderStatus::Pending, stock: 10, qty: 2);

        $product = $order->items()->firstOrFail()->product;
        $this->assertNotNull($product);
        $before = $product->stock_qty;

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', [
                'status' => OrderStatus::Cancelled->value,
                'note' => 'Customer changed their mind',
            ]);

        $order->refresh();
        $product->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame($before + 2, $product->stock_qty, 'cancelling must put the units back');

        // Cancellation is one of the three statuses the customer hears about.
        Mail::assertQueued(OrderStatusUpdated::class);
    }

    #[Test]
    public function marking_cod_paid_changes_payment_without_touching_the_order_status(): void
    {
        $this->actingAsAdmin();
        $order = $this->order(OrderStatus::Shipped);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('markPaid');

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        // A COD order ships unpaid; collecting the cash is not a status change.
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    #[Test]
    public function the_packing_slip_renders_a_real_pdf_for_a_real_order(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        // The view itself, rendered through dompdf exactly as the action does.
        // A Blade error here would otherwise only surface as a broken button.
        $bytes = Pdf::loadView('pdf.packing-slip', ['order' => $order->loadMissing(['items', 'customer'])])
            ->setPaper('a4')
            ->output();

        $this->assertStringStartsWith('%PDF', $bytes, 'the packing slip must be a real PDF');
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    #[Test]
    public function the_packing_slip_action_streams_a_download(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('packingSlip')
            ->assertFileDownloaded('packing-slip-'.$order->order_no.'.pdf');
    }

    #[Test]
    public function the_confirmation_email_can_be_resent(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('resendConfirmation');

        Mail::assertQueued(
            OrderPlacedCustomer::class,
            fn (OrderPlacedCustomer $mail): bool => $mail->order->is($order)
        );
    }

    #[Test]
    public function an_internal_note_is_the_only_field_the_dashboard_writes_directly(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('adminNote', ['admin_note' => 'Courier called twice, no answer.']);

        $order->refresh();

        $this->assertSame('Courier called twice, no answer.', $order->admin_note);
        $this->assertSame(OrderStatus::Pending, $order->status, 'a note must not move the order');
    }

    #[Test]
    public function global_search_finds_an_order_by_the_phone_number_as_a_customer_says_it(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();

        // Stored canonically as +966551234567. Staff type what they are told.
        $results = OrderResource::getGlobalSearchResults('0551234567');

        $this->assertGreaterThan(0, $results->count(), 'a local-format phone number must find the order');
        $this->assertSame($order->order_no, $results->first()?->title);
    }

    #[Test]
    public function global_search_finds_an_order_by_number_and_by_email(): void
    {
        $this->actingAsAdmin();
        $order = $this->order();
        $email = $order->customer->email;

        $this->assertSame($order->order_no, OrderResource::getGlobalSearchResults($order->order_no)->first()?->title);
        $this->assertSame($order->order_no, OrderResource::getGlobalSearchResults($email)->first()?->title);
    }
}
