<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\ShippingDetailsRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingCity;
use App\Models\ShippingZone;
use App\Services\CartService;
use App\Services\CheckoutDetails;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentGateways;
use App\Services\ShippingCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Review → Shipping → Payment → Confirmation.
 *
 * Each step is its own URL so the browser's back button behaves, and the
 * session holds the progress. A step reached out of order redirects back to
 * the earliest incomplete one rather than rendering a half-filled form.
 */
final class CheckoutController extends Controller
{
    private const SESSION_DETAILS = 'checkout.details';

    private const SESSION_TOKEN = 'checkout.token';

    private const SESSION_ORDERS = 'checkout.orders';

    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly ShippingCalculator $shipping,
        private readonly PaymentGateways $gateways,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('checkout.review');
    }

    public function review(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('checkout.review', [
            'step' => 'review',
            'review' => $this->checkout->review($cart),
        ]);
    }

    public function shipping(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $review = $this->checkout->review($cart);
        $saved = $request->session()->get(self::SESSION_DETAILS, []);

        return view('checkout.shipping', [
            'step' => 'shipping',
            'review' => $review,
            'zones' => ShippingZone::query()->active()->orderBy('sort_order')->get(),

            /*
             | EVERY active city is rendered, grouped by zone — not just the
             | ones for an already-chosen area.
             |
             | Sending an empty list until a zone is picked assumes JavaScript
             | will refill it. Without JS the visitor could choose an area and
             | then had no city to select, so checkout was impossible to
             | complete at all (§9: the forms must work with JS disabled).
             | shop.js narrows this list on zone change; the server rejects a
             | city that does not belong to the chosen zone either way.
             */
            'cities' => ShippingCity::query()
                ->active()
                ->whereHas('zone', fn ($q) => $q->where('is_active', true))
                ->orderBy('zone_id')
                ->orderBy('name_en')
                ->get()
                ->groupBy('zone_id'),

            'saved' => $saved,
        ]);
    }

    public function storeShipping(ShippingDetailsRequest $request): RedirectResponse
    {
        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $validated = $request->validated();
        $validated['phone'] = $request->normalisedPhone();

        $city = ShippingCity::query()->findOrFail($validated['city_id']);

        $details = CheckoutDetails::fromValidated($validated, $city->displayName());

        $request->session()->put(self::SESSION_DETAILS, $details->toSession());

        return redirect()->route('checkout.payment');
    }

    public function payment(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $saved = $request->session()->get(self::SESSION_DETAILS);

        if (! is_array($saved) || $saved === []) {
            return redirect()->route('checkout.shipping');
        }

        $details = CheckoutDetails::fromSession($saved);
        $review = $this->checkout->review($cart);
        $zone = ShippingZone::query()->find($details->zoneId);
        $quote = $this->shipping->quote($zone, $review->subtotal);

        // Issued per render and cleared by the first successful placement, so
        // a double submit (or a back-then-resubmit) cannot create two orders.
        $token = (string) Str::uuid();
        $request->session()->put(self::SESSION_TOKEN, $token);

        return view('checkout.payment', [
            'step' => 'payment',
            'review' => $review,
            'details' => $details,
            'quote' => $quote,
            'grandTotal' => $this->shipping->grandTotal(
                $review->subtotal,
                $quote->available ? $quote->fee : '0.00'
            ),
            'checkoutToken' => $token,
            'codEnabled' => $this->gateways->isEnabled(PaymentMethod::CashOnDelivery),
        ]);
    }

    public function placeOrder(PlaceOrderRequest $request): RedirectResponse
    {
        $expected = $request->session()->get(self::SESSION_TOKEN);
        $tokenMatches = is_string($expected)
            && hash_equals($expected, (string) $request->input('checkout_token'));

        /*
         | The spent-token case is handled FIRST, before the cart and details
         | checks, and the order matters.
         |
         | Placing an order deletes the cart. So on a double submit — a
         | double-click, or back-then-resubmit — the later request has no cart,
         | and checking that first sent the shopper to an empty cart page
         | moments after they had successfully paid. No duplicate order was
         | created, but the outcome read like a failure. Answering the
         | resubmission with the order they actually placed is the truthful
         | response.
         */
        if (! $tokenMatches) {
            $last = $this->lastOrderNo($request);

            if ($last !== null) {
                return redirect()->route('checkout.confirmation', ['order_no' => $last]);
            }
        }

        $cart = $this->carts->current($request);

        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $saved = $request->session()->get(self::SESSION_DETAILS);

        if (! is_array($saved) || $saved === []) {
            return redirect()->route('checkout.shipping');
        }

        if (! $tokenMatches) {
            return redirect()->route('checkout.review')
                ->withErrors(['checkout' => CheckoutException::alreadyPlaced()->getMessage()]);
        }

        try {
            $order = $this->checkout->place(
                cart: $cart,
                details: CheckoutDetails::fromSession($saved),
                // The gateway for the method the shopper chose, via the
                // registry — never a concrete class named here.
                gateway: $this->gateways->for(PaymentMethod::from((string) $request->validated('payment_method'))),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (CheckoutException $e) {
            return redirect()->route('checkout.review')->withErrors(['checkout' => $e->getMessage()]);
        }

        // Spend the token and remember which orders this session may view.
        $request->session()->forget(self::SESSION_TOKEN);
        $request->session()->forget(self::SESSION_DETAILS);
        $request->session()->push(self::SESSION_ORDERS, $order->order_no);

        return redirect()->route('checkout.confirmation', ['order_no' => $order->order_no]);
    }

    /**
     * Only the session that placed an order may read it back. Order numbers
     * are unguessable by design, but that is not on its own an access control.
     */
    public function confirmation(Request $request, string $order_no): View
    {
        $permitted = $request->session()->get(self::SESSION_ORDERS, []);

        if (! is_array($permitted) || ! in_array($order_no, $permitted, true)) {
            throw new NotFoundHttpException('That order is not available from this session.');
        }

        $order = Order::query()
            ->with(['items', 'customer', 'shippingZone.activeRate'])
            ->where('order_no', $order_no)
            ->firstOrFail();

        $rate = $order->shippingZone?->activeRate;

        return view('checkout.confirmation', [
            'order' => $order,
            'deliveryEstimate' => $rate?->estimateLabel(),
        ]);
    }

    private function lastOrderNo(Request $request): ?string
    {
        $orders = $request->session()->get(self::SESSION_ORDERS, []);

        if (! is_array($orders) || $orders === []) {
            return null;
        }

        return (string) end($orders);
    }
}
