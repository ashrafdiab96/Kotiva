<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingCity;
use App\Models\ShippingZone;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Populates the city select when a delivery area is chosen (§6.6).
 *
 * The shipping quote travels with the cities so the fee and delivery estimate
 * can update in the same round trip — asking twice would let the shopper see a
 * city list and a stale fee at the same moment.
 */
final class ShippingCityApiController extends Controller
{
    public function __construct(private readonly ShippingCalculator $shipping) {}

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

        // Subtotal is optional: without it the caller still gets the city list,
        // just no free-shipping determination.
        $subtotal = $request->has('subtotal')
            ? number_format((float) $request->input('subtotal'), 2, '.', '')
            : '0.00';

        $quote = $this->shipping->quote($zone, $subtotal);

        return response()->json([
            'cities' => $cities,
            'shipping' => [
                'available' => $quote->available,
                'fee' => $quote->fee,
                'is_free' => $quote->isFree,
                'fee_label' => $quote->feeLabel((string) config('kotiva.currency.code')),
                'estimate' => $quote->estimateLabel,
                'free_shipping_threshold' => $quote->freeShippingThreshold,
                'amount_to_free_shipping' => $this->shipping->amountToFreeShipping($zone, $subtotal),
            ],
        ]);
    }
}
