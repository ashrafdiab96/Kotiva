@extends('layouts.app')

@section('meta')
  <title>Review Your Order — kotiva™</title>
  <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<section data-theme="ritual" class="checkout-page">
  <div class="container">
    <header class="checkout-head">
      <p class="t-eyebrow mb-16">Checkout</p>
      <h1 class="t-display t-display-md">Review</h1>
    </header>

    @include('checkout._stepper', ['step' => $step])

    @error('checkout')
      <p class="cart-error" role="alert">{{ $message }}</p>
    @enderror

    {{-- Price and availability changes are stated before the shopper commits,
         never quietly applied at payment. --}}
    @if ($review->hasWarnings())
      <div class="checkout-warning" role="alert">
        @foreach ($review->unavailableLines() as $line)
          <p><strong>{{ $line->product->displayName() }}</strong> is no longer available and will be removed.</p>
        @endforeach
        @foreach ($review->changedLines() as $line)
          <p>
            <strong>{{ $line->product->displayName() }}</strong>
            {{ $line->priceWentUp() ? 'has increased' : 'has decreased' }} from
            {{ config('kotiva.currency.code') }} {{ $line->previousPrice }} to
            {{ config('kotiva.currency.code') }} {{ $line->unitPrice }}.
          </p>
        @endforeach
      </div>
    @endif

    <div class="checkout-layout">
      <div class="checkout-main">
        <div class="checkout-panel">
          <div class="checkout-panel-head">
            <h2 class="checkout-panel-title">Your Items</h2>
            <a class="checkout-edit" href="{{ route('cart.index') }}">Edit cart</a>
          </div>

          <div class="cart-items">
            @foreach ($review->lines as $line)
              <div class="cart-item">
                <a class="cart-item-img" href="{{ route('product.show', ['slug' => $line->product->slug]) }}">
                  <img src="{{ $line->product->imageUrl() }}" alt="{{ $line->product->displayName() }}" loading="lazy" />
                </a>
                <div class="cart-item-body">
                  <a class="cart-item-name" href="{{ route('product.show', ['slug' => $line->product->slug]) }}">{{ $line->product->displayName() }}</a>
                  <div class="cart-item-meta">{{ $line->product->category->name }}@if ($line->product->volume) &middot; {{ $line->product->volume }}@endif</div>
                  <div class="cart-item-unit">{{ config('kotiva.currency.code') }} {{ $line->unitPrice }} &times; {{ $line->item->qty }}</div>
                  @if ($line->unavailable)
                    <p class="cart-item-warn">No longer available</p>
                  @endif
                </div>
                <div class="cart-item-total">{{ config('kotiva.currency.code') }} {{ $line->lineTotal }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <aside class="cart-summary" aria-label="Order summary">
        <h2 class="cart-summary-title">Summary</h2>

        <div class="cart-summary-row">
          <span>Subtotal</span>
          <span>{{ config('kotiva.currency.code') }} {{ $review->subtotal }}</span>
        </div>

        <div class="cart-summary-row cart-summary-muted">
          <span>Shipping</span>
          <span>Calculated next</span>
        </div>

        <p class="cart-summary-note">All prices include 15% VAT.</p>

        <a class="btn btn-primary cart-checkout" href="{{ route('checkout.shipping') }}">Continue to Shipping</a>
        <a class="btn btn-outline cart-continue" href="{{ route('cart.index') }}">Back to Cart</a>

        <p class="cart-summary-cod">Cash on delivery available.</p>
      </aside>
    </div>
  </div>
</section>
@endsection
