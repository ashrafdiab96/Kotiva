<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Services\Payments\PaymentGateway;
use App\Support\OrderNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a cart into an order.
 *
 * Two rules drive the whole class:
 *
 *   1. The cart's price snapshots are EVIDENCE, never the amount charged. Every
 *      line is re-priced from the live product at placement, and the review
 *      step surfaces anything that moved. A shopper must never be charged a
 *      figure they were not shown, in either direction.
 *
 *   2. Placement is one transaction. Re-validation, the order insert, the item
 *      inserts and the stock fulfilment either all happen or none do — a
 *      half-placed order is worse than a refused one.
 */
final class CheckoutService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ShippingCalculator $shipping,
    ) {}

    /**
     * What the review step shows: live prices, and anything the shopper needs
     * to know before committing.
     */
    public function review(Cart $cart): CheckoutReview
    {
        $cart->loadMissing('items.product');

        $lines = [];
        $subtotal = '0.00';

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product instanceof Product) {
                continue;
            }

            $lineTotal = $item->lineTotal();
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            $lines[] = new CheckoutLine(
                item: $item,
                product: $product,
                unitPrice: $item->unitPrice(),
                lineTotal: $lineTotal,
                priceChanged: $item->priceHasChanged(),
                previousPrice: number_format((float) $item->unit_price_snapshot, 2, '.', ''),
                unavailable: ! $product->is_active,
            );
        }

        return new CheckoutReview($lines, $subtotal);
    }

    /**
     * Place the order.
     *
     * @throws CheckoutException
     */
    public function place(
        Cart $cart,
        CheckoutDetails $details,
        PaymentGateway $gateway,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Order {
        if (! $gateway->isEnabled()) {
            throw CheckoutException::paymentUnavailable();
        }

        $cart->loadMissing('items.product');

        if ($cart->items->isEmpty()) {
            throw CheckoutException::emptyCart();
        }

        $order = DB::transaction(function () use ($cart, $details, $gateway, $ipAddress, $userAgent): Order {
            $zone = ShippingZone::query()->find($details->zoneId);

            // Re-read and lock every product, then re-check it. Anything read
            // before the lock is already stale.
            $lines = [];
            $subtotal = '0.00';

            foreach ($cart->items as $item) {
                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product instanceof Product || ! $product->is_active) {
                    throw CheckoutException::productUnavailable(
                        $item->product instanceof Product ? $item->product->displayName() : 'An item in your cart'
                    );
                }

                $unitPrice = number_format((float) $product->price, 2, '.', '');
                $lineTotal = bcmul($unitPrice, (string) $item->qty, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $lines[] = [$item, $product, $unitPrice, $lineTotal];
            }

            $quote = $this->shipping->quote($zone, $subtotal);
            $shippingFee = $quote->available ? $quote->fee : '0.00';
            $grandTotal = $this->shipping->grandTotal($subtotal, $shippingFee);

            $customer = $this->customerFor($details);

            $order = Order::create([
                'order_no' => OrderNumber::generate(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::Pending,
                'payment_method' => PaymentMethod::from($gateway->method()),
                'payment_status' => $gateway->initialStatus(),
                'currency' => config('kotiva.currency.code'),
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount_total' => '0.00',
                // Extracted from the inclusive total, never added to it. Read
                // through Setting so the dashboard's VAT field actually
                // governs new orders; config remains the fallback before any
                // row exists.
                'vat_amount' => Order::vatPortionOf(
                    $grandTotal,
                    (float) Setting::get('vat_rate', config('kotiva.vat_rate'))
                ),
                'grand_total' => $grandTotal,
                'shipping_zone_id' => $details->zoneId,
                'shipping_city_id' => $details->cityId,
                'shipping_name' => $details->fullName(),
                'shipping_phone' => $details->phone,
                'shipping_address_line1' => $details->addressLine1,
                'shipping_address_line2' => $details->addressLine2,
                'shipping_district' => $details->district,
                'shipping_city_name' => $details->cityName,
                'shipping_postal_code' => $details->postalCode,
                'customer_note' => $details->note,
                'placed_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            ]);

            foreach ($lines as [$item, $product, $unitPrice, $lineTotal]) {
                /** @var CartItem $item */
                /** @var Product $product */
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku_snapshot' => $product->sku,
                    'name_snapshot' => $product->name,
                    'image_snapshot' => $product->image,
                    'unit_price' => $unitPrice,
                    'qty' => $item->qty,
                    'line_total' => $lineTotal,
                ]);

                // The units already left stock when they were reserved at
                // add-to-cart; this closes the reservation against the order
                // rather than decrementing a second time.
                $this->stock->fulfil($product, $item->qty, $order);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::Pending,
                'note' => 'Order placed',
            ]);

            // The cart's reservations are now the order's. Delete the lines
            // directly: releasing them would hand the stock back after it has
            // already been sold.
            $cart->items()->delete();
            $cart->delete();

            Log::info('Order placed', [
                'order_no' => $order->order_no,
                'customer_id' => $customer->id,
                'grand_total' => $grandTotal,
                'items' => count($lines),
            ]);

            return $order->fresh(['items']);
        });

        /*
         | Dispatched AFTER the transaction commits, deliberately.
         |
         | The listeners send mail. A queued listener runs on its own
         | connection and would not find an order still inside an uncommitted
         | transaction — and if the transaction then rolled back, a customer
         | would hold a confirmation for an order that does not exist.
         */
        OrderPlaced::dispatch($order);

        return $order;
    }

    /**
     * One customer record per email address.
     *
     * Details from the newest order win, so a shopper who moves house or
     * changes number is not stuck with what they typed the first time.
     */
    private function customerFor(CheckoutDetails $details): Customer
    {
        $customer = Customer::query()->where('email', $details->email)->first();

        if ($customer instanceof Customer) {
            $customer->update([
                'first_name' => $details->firstName,
                'last_name' => $details->lastName,
                'phone' => $details->phone,
                // Opting in is remembered; opting out of one order does not
                // silently revoke a previous consent.
                'marketing_opt_in' => $customer->marketing_opt_in || $details->marketingOptIn,
            ]);

            return $customer;
        }

        return Customer::create([
            'first_name' => $details->firstName,
            'last_name' => $details->lastName,
            'email' => $details->email,
            'phone' => $details->phone,
            'marketing_opt_in' => $details->marketingOptIn,
        ]);
    }
}
