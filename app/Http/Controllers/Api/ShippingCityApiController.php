<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ShippingCity;
use App\Models\ShippingZone;
use App\Services\CartService;
use App\Services\ShippingCalculator;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Populates the city select when a delivery area is chosen (§6.6).
 *
 * The shipping quote travels with the cities so the fee and delivery estimate
 * can update in the same round trip — asking twice would let the shopper see a
 * city list and a stale fee at the same moment.
 *
 * The quote is priced from the server's own cart. The browser sends a zone and
 * nothing else that touches money.
 */
final class ShippingCityApiController extends Controller
{
    public function __construct(
        private readonly ShippingCalculator $shipping,
        private readonly CartService $carts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $zoneId = $request->integer('zone_id');

        $zone = ShippingZone::query()->active()->find($zoneId);

        if (! $zone instanceof ShippingZone) {
            return response()->json(['cities' => [], 'shipping' => null]);
        }

        $cities = ShippingCity::query()
            ->where('zone_id', $zone->id)
            ->active()
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar'])
            ->map(fn (ShippingCity $city): array => [
                'id' => $city->id,
                'name' => $city->displayName(),
            ])
            ->values();

        /*
         | The free-shipping determination is made against the SERVER'S cart,
         | never against a subtotal the browser sends.
         |
         | This endpoint used to accept `?subtotal=`, which meant anyone could
         | ask it to confirm free delivery on a 10.00 basket. Nothing was
         | charged on that answer — checkout re-quotes from the cart on submit —
         | but a shopper shown "Free delivery" and then charged 25.00 has been
         | misled, and the fix costs one lookup. Which of the two merchandise
         | totals the threshold is measured against is the configured business
         | rule; see App\Support\Vat::freeShippingBasis().
         */
        $cart = $this->carts->current($request);

        $basis = $cart instanceof Cart
            ? $cart->totals()->freeShippingBasisAmount()
            : Money::zero();

        $quote = $this->shipping->quote($zone, $basis);

        return response()->json([
            'cities' => $cities,
            'shipping' => [
                'available' => $quote->available,
                'fee' => $quote->fee,
                'is_free' => $quote->isFree,
                'fee_label' => $quote->feeLabel((string) config('kotiva.currency.code')),
                'estimate' => $quote->estimateLabel,
                'free_shipping_threshold' => $quote->freeShippingThreshold,
                'amount_to_free_shipping' => $this->shipping->amountToFreeShipping($zone, $basis),
            ],
        ]);
    }
}
