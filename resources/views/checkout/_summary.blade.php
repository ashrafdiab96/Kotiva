{{--
  The checkout money breakdown, shared by Review, Shipping and Payment.

  One partial rather than three copies, because the whole point of the
  breakdown is that it reads identically at every step: a shopper who sees
  merchandise stated one way on Review and another on Payment has no reason to
  trust either. Every figure comes from the server-side MoneyTotals passed in —
  nothing here adds anything up.

  @param \App\Services\MoneyTotals $totals
  @param string|null $shippingSlot  markup for the delivery value (the shipping
                                    step swaps in a live-updating element)
  @param string|null $shippingNote
--}}
@php
    $currency = config('kotiva.currency.code');
@endphp

{{-- Merchandise is stated BOTH ways: net, because that is the price on every
     product card the shopper clicked, and gross, because that is what delivery
     is added to and what they will actually pay. --}}
<div class="cart-summary-row">
  <span>Merchandise <small>excl. VAT</small></span>
  <span>{{ $currency }} {{ $totals->merchandiseExclVat }}</span>
</div>

<div class="cart-summary-row">
  <span>VAT ({{ $totals->vatRateLabel() }})</span>
  <span>{{ $currency }} {{ $totals->vatTotal }}</span>
</div>

{{-- Only once delivery is known. Before that the total row below carries this
     same figure, and printing it twice in a row reads as a mistake. --}}
@if ($totals->shippingResolved)
  <div class="cart-summary-row">
    <span>Merchandise <small>incl. VAT</small></span>
    <span>{{ $currency }} {{ $totals->merchandiseInclVat }}</span>
  </div>
@endif

<div class="cart-summary-row{{ $totals->shippingResolved ? '' : ' cart-summary-muted' }}">
  <span>Shipping</span>
  @if (($shippingSlot ?? null) !== null)
    {!! $shippingSlot !!}
  @else
    <span>{{ $totals->shippingLabel($currency, $undetermined ?? 'Calculated next') }}</span>
  @endif
</div>

@if ($totals->hasDiscount())
  <div class="cart-summary-row">
    <span>Discount</span>
    <span>&minus; {{ $currency }} {{ $totals->discountTotal }}</span>
  </div>
@endif

{{-- Only rendered once delivery is known. Before that there is no payable
     figure, and printing one would be a guess the shopper would read as a
     promise. --}}
@if ($totals->shippingResolved)
  <div class="cart-summary-row cart-summary-total">
    <span>Total to pay</span>
    <span>{{ $currency }} {{ $totals->grandTotal }}</span>
  </div>
@else
  <div class="cart-summary-row cart-summary-total">
    <span>Total <small>incl. VAT</small></span>
    <span>{{ $currency }} {{ $totals->merchandiseInclVat }}</span>
  </div>
@endif

@if (($shippingNote ?? null) !== null)
  <p class="cart-summary-note">{{ $shippingNote }}</p>
@endif

{{-- "Product prices include VAT" would be ambiguous directly beneath a line
     labelled "excl. VAT" — it has to say WHICH prices. The item prices on this
     page are the VAT-inclusive ones; the shop's are not. --}}
<p class="cart-summary-note">
  Item prices on this page include {{ $totals->vatRateLabel() }} VAT.
  {{ $currency }} {{ $totals->vatTotal }} of this order is VAT.
  @unless ($totals->shippingResolved)
    Delivery is added once your area is chosen.
  @endunless
</p>
