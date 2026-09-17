@extends('layouts.app')

@section('meta')
  <title>Order Confirmed — kotiva™</title>
  <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<section data-theme="ritual" class="checkout-page">
  <div class="container">
    <div class="confirmation-head">
      <p class="t-eyebrow mb-16">Thank You</p>
      <h1 class="t-display t-display-md">Order Confirmed</h1>
      <p class="confirmation-lede">
        We have received your order and sent a confirmation to
        <strong>{{ $order->customer->email }}</strong>.
      </p>

      <div class="confirmation-number">
        <span class="confirmation-number-label">Order Number</span>
        <span class="confirmation-number-value">{{ $order->order_no }}</span>
      </div>

      @if ($deliveryEstimate)
        <p class="confirmation-estimate">Estimated delivery: {{ $deliveryEstimate }}.</p>
      @endif
      <p class="confirmation-estimate">Payment: {{ $order->payment_method->label() }} — please have the exact amount ready.</p>
    </div>

    <div class="checkout-layout">
      <div class="checkout-main">
        <div class="checkout-panel">
          <div class="checkout-panel-head">
            <h2 class="checkout-panel-title">Your Order</h2>
          </div>

          <div class="cart-items">
            @foreach ($order->items as $item)
              <div class="cart-item">
                <div class="cart-item-img">
                  @if ($item->image_snapshot)
                    <img src="{{ $item->imageUrl() }}" alt="{{ $item->name_snapshot }}" loading="lazy" />
                  @endif
                </div>
                <div class="cart-item-body">
                  {{-- Snapshots, not live product data: this must keep reading
                       the same way even after the catalog changes. --}}
                  <span class="cart-item-name">{{ $item->name_snapshot }}</span>
                  <div class="cart-item-meta">{{ $item->sku_snapshot }}</div>
                  <div class="cart-item-unit">{{ $order->currency }} {{ $item->unit_price }} &times; {{ $item->qty }}</div>
                </div>
                <div class="cart-item-total">{{ $order->currency }} {{ $item->line_total }}</div>
              </div>
            @endforeach
          </div>
        </div>

        <div class="checkout-panel">
          <div class="checkout-panel-head">
            <h2 class="checkout-panel-title">Delivery Address</h2>
          </div>
          <address class="checkout-address">
            {{ $order->shipping_name }}<br>
            {{ $order->formattedAddress() }}<br>
            {{ $order->shipping_phone }}
          </address>
          @if ($order->customer_note)
            <p class="checkout-address-note"><strong>Note:</strong> {{ $order->customer_note }}</p>
          @endif
        </div>
      </div>

      <aside class="cart-summary" aria-label="Order totals">
        <h2 class="cart-summary-title">Totals</h2>

        <div class="cart-summary-row">
          <span>Subtotal</span>
          <span>{{ $order->currency }} {{ $order->subtotal }}</span>
        </div>

        <div class="cart-summary-row">
          <span>Shipping</span>
          <span>{{ bccomp((string) $order->shipping_fee, '0.00', 2) === 0 ? 'Free' : $order->currency.' '.$order->shipping_fee }}</span>
        </div>

        <p class="cart-summary-note">Includes {{ $order->currency }} {{ $order->vat_amount }} VAT.</p>

        <div class="cart-summary-row cart-summary-total">
          <span>Total</span>
          <span>{{ $order->currency }} {{ $order->grand_total }}</span>
        </div>

        <a class="btn btn-primary cart-checkout" href="{{ route('shop.index') }}">Continue Shopping</a>
        <a class="btn btn-outline cart-continue" href="{{ route('contact') }}">Need Help?</a>
      </aside>
    </div>
  </div>
</section>
@endsection
