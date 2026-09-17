@extends('layouts.app')

@section('meta')
  <title>Your Cart — kotiva™</title>
  <meta name="description" content="Review the items in your kotiva™ cart before checking out.">
  {{-- A cart is per-visitor and has no business in an index. --}}
  <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<section data-theme="ritual" class="cart-page">
  <div class="container">
    <header class="cart-head">
      <p class="t-eyebrow mb-16">Your Selection</p>
      <h1 class="t-display t-display-md">Cart</h1>
    </header>

    @if (session('cart_status'))
      <p class="cart-status" role="status">{{ session('cart_status') }}</p>
    @endif

    @error('qty')
      <p class="cart-error" role="alert">{{ $message }}</p>
    @enderror

    @if ($items->isEmpty())
      {{-- Empty state: a dead end with no way forward is the most common cart
           mistake, so this leads straight back into the range. --}}
      <div class="cart-empty">
        <p class="cart-empty-line">Your cart is empty.</p>
        <p class="t-body">Nothing here yet — the full range is a click away.</p>
        <a class="btn btn-primary" href="{{ route('shop.index') }}">Discover the Range</a>
      </div>
    @else
      <div class="cart-layout">
        <div class="cart-items" role="list">
          @foreach ($items as $item)
            <div class="cart-item" role="listitem">
              <a class="cart-item-img" href="{{ route('product.show', ['slug' => $item->product->slug]) }}">
                <img src="{{ $item->product->imageUrl() }}" alt="{{ $item->product->displayName() }}" loading="lazy" />
              </a>

              <div class="cart-item-body">
                <a class="cart-item-name" href="{{ route('product.show', ['slug' => $item->product->slug]) }}">{{ $item->product->displayName() }}</a>
                <div class="cart-item-meta">{{ $item->product->category->name }}@if ($item->product->volume) &middot; {{ $item->product->volume }}@endif</div>
                <div class="cart-item-unit">{{ config('kotiva.currency.code') }} {{ $item->unitPrice() }} <small>VAT incl.</small></div>

                @if ($item->product->isSoldOut())
                  <p class="cart-item-warn">This product is now sold out.</p>
                @elseif ($item->product->isLowStock())
                  <p class="cart-item-warn">Only {{ $item->product->stock_qty }} left.</p>
                @endif
              </div>

              {{-- Plain forms, so quantity and removal work with JS disabled.
                   shop.js intercepts these and updates in place. --}}
              <form class="cart-item-qty" method="POST" action="{{ route('cart.items.update', ['item' => $item->getKey()]) }}" data-cart-update>
                @csrf
                @method('PATCH')
                <label class="visually-hidden" for="qty-{{ $item->getKey() }}">Quantity for {{ $item->product->displayName() }}</label>
                <input
                  class="cart-qty-input"
                  id="qty-{{ $item->getKey() }}"
                  type="number"
                  name="qty"
                  value="{{ $item->qty }}"
                  min="0"
                  max="{{ max($item->qty, $item->product->maxOrderableQty()) }}"
                  inputmode="numeric"
                />
                <button class="cart-qty-apply" type="submit">Update</button>
              </form>

              <div class="cart-item-total">{{ config('kotiva.currency.code') }} {{ $item->lineTotal() }}</div>

              <form method="POST" action="{{ route('cart.items.destroy', ['item' => $item->getKey()]) }}" data-cart-remove>
                @csrf
                @method('DELETE')
                <button class="cart-item-remove" type="submit" aria-label="Remove {{ $item->product->displayName() }} from cart">Remove</button>
              </form>
            </div>
          @endforeach
        </div>

        <aside class="cart-summary" aria-label="Order summary">
          <h2 class="cart-summary-title">Summary</h2>

          <div class="cart-summary-row">
            <span>Subtotal</span>
            <span data-cart-subtotal>{{ config('kotiva.currency.code') }} {{ $cart->subtotal() }}</span>
          </div>

          <div class="cart-summary-row cart-summary-muted">
            <span>Shipping</span>
            <span>Calculated at checkout</span>
          </div>

          <p class="cart-summary-note">All prices include 15% VAT.</p>

          <div class="cart-summary-row cart-summary-total">
            <span>Total</span>
            <span data-cart-total>{{ config('kotiva.currency.code') }} {{ $cart->subtotal() }}</span>
          </div>

          <a class="btn btn-primary cart-checkout" href="{{ route('checkout.index') }}">Proceed to Checkout</a>
          <a class="btn btn-outline cart-continue" href="{{ route('shop.index') }}">Continue Shopping</a>

          <p class="cart-summary-cod">Cash on delivery available.</p>
        </aside>
      </div>
    @endif
  </div>
</section>
@endsection
