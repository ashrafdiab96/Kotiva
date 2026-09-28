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
use App\Models\ShippingZone;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
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

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product instanceof Product) {
                continue;
            }

            $lines[] = CheckoutLine::fromCartItem($item, $product);
        }

        return CheckoutReview::fromLines($lines);
    }

    /**
     * The review with a delivery quote applied, giving the full breakdown the
     * shipping, payment and confirmation steps all display.
     *
     * One method rather than each controller action assembling its own totals:
     * the shopper sees this figure three times before they commit to it, and
     * it must be the same figure each time and the same one that `place()`
     * charges.
     */
    public function reviewWithShipping(Cart $cart, ?ShippingZone $zone): CheckoutReview
    {
        $review = $this->review($cart);

        return $review->withTotals($this->shipping->totals(
            $zone,
            $review->subtotalExclVat(),
            $review->subtotalInclVat(),
        ));
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

            /*
             | Re-read and lock every product, then re-price BOTH figures from
             | the locked row. Anything read before the lock is already stale,
             | and nothing the browser submitted is consulted at any point —
             | the request carries an address and a payment method, never an
             | amount.
             */
            $lines = [];
            $merchandiseExclVat = Money::zero();
            $merchandiseInclVat = Money::zero();

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

                $unitPriceInclVat = $product->priceInclVat();
                $unitPriceExclVat = $product->priceExclVat();

                $lineTotalInclVat = Money::multiplyByQty($unitPriceInclVat, $item->qty);
                $lineTotalExclVat = Money::multiplyByQty($unitPriceExclVat, $item->qty);

                $merchandiseInclVat = Money::add($merchandiseInclVat, $lineTotalInclVat);
                $merchandiseExclVat = Money::add($merchandiseExclVat, $lineTotalExclVat);

                $lines[] = [$item, $product, $unitPriceInclVat, $unitPriceExclVat, $lineTotalInclVat, $lineTotalExclVat];
            }

            // One breakdown, computed once, inside the lock. Every figure
            // written to the order below comes from here.
            $totals = $this->shipping->totals($zone, $merchandiseExclVat, $merchandiseInclVat);

            $customer = $this->customerFor($details);

            $order = Order::create([
                'order_no' => OrderNumber::generate(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::Pending,
                'payment_method' => PaymentMethod::from($gateway->method()),
                'payment_status' => $gateway->initialStatus(),
                'currency' => config('kotiva.currency.code'),
                /*
                 | The order's immutable money snapshot. `subtotal` keeps its
                 | original meaning — merchandise VAT-INCLUDED — so every
                 | receipt, packing slip and export already in circulation
                 | still reads correctly; the net figure is stored alongside it
                 | rather than replacing it.
                 |
                 | Product VAT is the difference between the two authoritative
                 | brochure prices, not a recomputation at the rate: it is the
                 | VAT actually contained in what was charged. Shipping VAT is
                 | separate and follows the configured delivery treatment.
                 | Neither is ever added to the total — vat_amount reports what
                 | is inside grand_total.
                 */
                'subtotal' => $totals->merchandiseInclVat,
                'subtotal_excl_vat' => $totals->merchandiseExclVat,
                'shipping_fee' => $totals->shippingFee,
                'discount_total' => $totals->discountTotal,
                'vat_amount' => $totals->vatTotal,
                'product_vat_amount' => $totals->productVat,
                'shipping_vat_amount' => $totals->shippingVat,
                // The rate is snapshotted too, so a receipt reprinted after a
                // rate change still describes the transaction that happened.
                'vat_rate' => $totals->vatRate,
                'grand_total' => $totals->grandTotal,
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

            foreach ($lines as [$item, $product, $unitPriceInclVat, $unitPriceExclVat, $lineTotalInclVat, $lineTotalExclVat]) {
                /** @var CartItem $item */
                /** @var Product $product */
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku_snapshot' => $product->sku,
                    'name_snapshot' => $product->name,
                    'image_snapshot' => $product->image,
                    // Both prices are snapshotted. unit_price and line_total
                    // keep their original VAT-inclusive meaning, so existing
                    // receipts and slips are unaffected; the net figures and
                    // the line's own VAT sit beside them, because credit notes
                    // and partial refunds work a line at a time and must not
                    // re-derive an old line at today's rate.
                    'unit_price' => $unitPriceInclVat,
                    'unit_price_excl_vat' => $unitPriceExclVat,
                    'qty' => $item->qty,
                    'line_total' => $lineTotalInclVat,
                    'line_total_excl_vat' => $lineTotalExclVat,
                    'vat_amount' => Money::sub($lineTotalInclVat, $lineTotalExclVat),
                    'vat_rate' => $totals->vatRate,
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
                'grand_total' => $totals->grandTotal,
                'vat_amount' => $totals->vatTotal,
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
