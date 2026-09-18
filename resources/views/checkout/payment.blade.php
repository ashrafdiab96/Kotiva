@extends('layouts.app')

@section('meta')
  <title>Payment — kotiva™</title>
  <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<section data-theme="ritual" class="checkout-page">
  <div class="container">
    <header class="checkout-head">
      <p class="t-eyebrow mb-16">Checkout</p>
      <h1 class="t-display t-display-md">Payment</h1>
    </header>

    @include('checkout._stepper', ['step' => $step])

    @error('checkout')
      <p class="cart-error" role="alert">{{ $message }}</p>
    @enderror

    <div class="checkout-layout">
      <div class="checkout-main">
        <div class="checkout-panel">
          <div class="checkout-panel-head">
            <h2 class="checkout-panel-title">Delivering To</h2>
            <a class="checkout-edit" href="{{ route('checkout.shipping') }}">Edit</a>
          </div>
          <address class="checkout-address">
            {{ $details->fullName() }}<br>
            {{ $details->addressLine1 }}<br>
            @if ($details->addressLine2){{ $details->addressLine2 }}<br>@endif
            @if ($details->district){{ $details->district }}<br>@endif
            {{ $details->cityName }}@if ($details->postalCode), {{ $details->postalCode }}@endif<br>
            {{ $details->phone }}<br>
            {{ $details->email }}
          </address>
        </div>

        <form class="checkout-form" method="POST" action="{{ route('checkout.payment.store') }}" data-checkout-place>
          @csrf
          <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}" />

          <div class="checkout-panel">
            <div class="checkout-panel-head">
              <h2 class="checkout-panel-title">Payment Method</h2>
            </div>

            @if ($codEnabled)
              <label class="checkout-method is-selected">
                <input type="radio" name="payment_method" value="cod" checked required @error('payment_method') aria-invalid="true" aria-describedby="payment_method-error" @enderror>
                <span class="checkout-method-body">
                  <span class="checkout-method-name">Cash on Delivery</span>
                  <span class="checkout-method-note">Pay the courier when your order arrives.</span>
                </span>
              </label>
            @else
              <p class="cart-error" role="alert">No payment method is currently available. Please contact us to complete your order.</p>
            @endif
            @error('payment_method')<p class="form-error" id="payment_method-error" role="alert">{{ $message }}</p>@enderror

            <label class="checkout-check">
              <input type="checkbox" name="terms" value="1" @checked(old('terms')) @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror>
              <span>I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</span>
            </label>
            {{-- role="alert": this is usually the only error on the step, and
                 without it a failed Place Order is silent to a screen reader. --}}
            @error('terms')<p class="form-error" id="terms-error" role="alert">{{ $message }}</p>@enderror

            <button type="submit" class="form-submit" @disabled(! $codEnabled)>Place Order</button>
          </div>
        </form>
      </div>

      <aside class="cart-summary" aria-label="Order summary">
        <h2 class="cart-summary-title">Summary</h2>

        @foreach ($review->lines as $line)
          <div class="cart-summary-row cart-summary-line">
            <span>{{ $line->product->displayName() }} &times; {{ $line->item->qty }}</span>
            <span>{{ config('kotiva.currency.code') }} {{ $line->lineTotal }}</span>
          </div>
        @endforeach

        <div class="cart-summary-row">
          <span>Subtotal</span>
          <span>{{ config('kotiva.currency.code') }} {{ $review->subtotal }}</span>
        </div>

        <div class="cart-summary-row">
          <span>Shipping</span>
          <span>{{ $quote->feeLabel((string) config('kotiva.currency.code')) }}</span>
        </div>

        @if ($quote->estimateLabel)
          <p class="cart-summary-note">Estimated delivery: {{ $quote->estimateLabel }}.</p>
        @endif

        <p class="cart-summary-note">All prices include 15% VAT.</p>

        <div class="cart-summary-row cart-summary-total">
          <span>Total</span>
          <span>{{ config('kotiva.currency.code') }} {{ $grandTotal }}</span>
        </div>

        <a class="btn btn-outline cart-continue" href="{{ route('checkout.shipping') }}">Back to Shipping</a>
      </aside>
    </div>
  </div>
</section>
@endsection
