@extends('layouts.app')

@section('meta')
  <title>Shipping Details — kotiva™</title>
  <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<section data-theme="ritual" class="checkout-page">
  <div class="container">
    <header class="checkout-head">
      <p class="t-eyebrow mb-16">Checkout</p>
      <h1 class="t-display t-display-md">Shipping</h1>
    </header>

    @include('checkout._stepper', ['step' => $step])

    <div class="checkout-layout">
      <div class="checkout-main">
        {{-- A plain POST form. shop.js adds the zone→city fetch and the live
             fee, but the server also renders the city list and recalculates on
             submit, so this works with JS off. --}}
        <form class="checkout-form" method="POST" action="{{ route('checkout.shipping.store') }}" data-checkout-shipping>
          @csrf
          <input type="hidden" name="subtotal" value="{{ $review->subtotal }}" />

          {{-- Announced on load, so a screen-reader user learns the submit
               failed; each message below is also tied to its own field. --}}
          @if ($errors->any())
            <p class="cart-error" role="alert">Please correct the {{ $errors->count() === 1 ? 'field' : $errors->count().' fields' }} marked below.</p>
          @endif

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="first_name">First Name</label>
              <input class="form-field @error('first_name') has-error @enderror" @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror id="first_name" name="first_name" type="text"
                     value="{{ old('first_name', $saved['first_name'] ?? '') }}" required>
              @error('first_name')<p class="form-error" id="first_name-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
              <label class="form-label" for="last_name">Last Name</label>
              <input class="form-field @error('last_name') has-error @enderror" @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror id="last_name" name="last_name" type="text"
                     value="{{ old('last_name', $saved['last_name'] ?? '') }}" required>
              @error('last_name')<p class="form-error" id="last_name-error">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <input class="form-field @error('email') has-error @enderror" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror id="email" name="email" type="email"
                     value="{{ old('email', $saved['email'] ?? '') }}" required>
              @error('email')<p class="form-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
              <label class="form-label" for="phone">Mobile Number</label>
              <input class="form-field @error('phone') has-error @enderror" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror id="phone" name="phone" type="tel"
                     inputmode="tel" placeholder="05XXXXXXXX"
                     value="{{ old('phone', $saved['phone'] ?? '') }}" required>
              @error('phone')<p class="form-error" id="phone-error">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="zone_id">Delivery Area</label>
              <select class="form-field @error('zone_id') has-error @enderror" @error('zone_id') aria-invalid="true" aria-describedby="zone_id-error" @enderror id="zone_id" name="zone_id" required data-shipping-zone>
                <option value="">Select an area</option>
                @foreach ($zones as $zone)
                  <option value="{{ $zone->id }}" @selected((int) old('zone_id', $saved['zone_id'] ?? 0) === $zone->id)>{{ $zone->name }}</option>
                @endforeach
              </select>
              @error('zone_id')<p class="form-error" id="zone_id-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
              <label class="form-label" for="city_id">City</label>
              {{-- Grouped by area so the whole list is usable without JS.
                   shop.js narrows it to the chosen area; the server validates
                   the pairing regardless of what was submitted. --}}
              <select class="form-field @error('city_id') has-error @enderror" @error('city_id') aria-invalid="true" aria-describedby="city_id-error" @enderror id="city_id" name="city_id" required data-shipping-city>
                <option value="">Select a city</option>
                @foreach ($zones as $zone)
                  @if (($cities[$zone->id] ?? collect())->isNotEmpty())
                    <optgroup label="{{ $zone->name }}" data-zone="{{ $zone->id }}">
                      @foreach ($cities[$zone->id] as $city)
                        <option value="{{ $city->id }}" data-zone="{{ $zone->id }}"
                                @selected((int) old('city_id', $saved['city_id'] ?? 0) === $city->id)>{{ $city->displayName() }}</option>
                      @endforeach
                    </optgroup>
                  @endif
                @endforeach
              </select>
              @error('city_id')<p class="form-error" id="city_id-error">{{ $message }}</p>@enderror
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="address_line1">Address</label>
            <input class="form-field @error('address_line1') has-error @enderror" @error('address_line1') aria-invalid="true" aria-describedby="address_line1-error" @enderror id="address_line1" name="address_line1" type="text"
                   value="{{ old('address_line1', $saved['address_line1'] ?? '') }}" required>
            @error('address_line1')<p class="form-error" id="address_line1-error">{{ $message }}</p>@enderror
          </div>

          <div class="form-group">
            <label class="form-label" for="address_line2">Address Line 2 <span class="form-optional">(optional)</span></label>
            <input class="form-field" id="address_line2" name="address_line2" type="text"
                   value="{{ old('address_line2', $saved['address_line2'] ?? '') }}">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="district">District</label>
              <input class="form-field @error('district') has-error @enderror" @error('district') aria-invalid="true" aria-describedby="district-error" @enderror id="district" name="district" type="text"
                     value="{{ old('district', $saved['district'] ?? '') }}">
              @error('district')<p class="form-error" id="district-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
              <label class="form-label" for="postal_code">Postal Code <span class="form-optional">(optional)</span></label>
              <input class="form-field" id="postal_code" name="postal_code" type="text"
                     value="{{ old('postal_code', $saved['postal_code'] ?? '') }}">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="note">Order Note <span class="form-optional">(optional)</span></label>
            <textarea class="form-field" id="note" name="note" rows="3">{{ old('note', $saved['note'] ?? '') }}</textarea>
          </div>

          <label class="checkout-check">
            <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $saved['marketing_opt_in'] ?? false))>
            <span>Email me skin insights and clinical updates. Unsubscribe anytime.</span>
          </label>

          <button type="submit" class="form-submit">Continue to Payment</button>
        </form>
      </div>

      <aside class="cart-summary" aria-label="Order summary">
        <h2 class="cart-summary-title">Summary</h2>

        <div class="cart-summary-row">
          <span>Subtotal</span>
          <span>{{ config('kotiva.currency.code') }} {{ $review->subtotal }}</span>
        </div>

        <div class="cart-summary-row cart-summary-muted">
          <span>Shipping</span>
          {{-- Filled in live once an area is chosen; the server recalculates
               on submit regardless of what was displayed. --}}
          <span data-shipping-fee>Select a delivery area</span>
        </div>

        <p class="cart-summary-note" data-shipping-estimate hidden></p>
        <p class="cart-summary-note">All prices include 15% VAT.</p>

        <a class="btn btn-outline cart-continue" href="{{ route('checkout.review') }}">Back to Review</a>
        <p class="cart-summary-cod">Cash on delivery available.</p>
      </aside>
    </div>
  </div>
</section>
@endsection
