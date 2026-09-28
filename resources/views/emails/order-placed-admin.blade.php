@extends('emails.layout')

@section('subject', 'New order '.$order->order_no.' — '.$order->currency.' '.$order->grand_total)

@section('preheader')
{{ $order->shipping_name }} ordered {{ $order->itemCount() }} item(s) for delivery to {{ $order->shipping_city_name }}.
@endsection

@php
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";

    /*
     | The dashboard deep link is rendered only once that route exists (it
     | arrives with Filament in phase 5). Emitting a hardcoded /admin URL now
     | would put a 404 in a live notification; this lights up on its own.
     */
    $adminRoute = 'filament.admin.resources.orders.view';
    $adminUrl = \Illuminate\Support\Facades\Route::has($adminRoute)
        ? route($adminRoute, ['record' => $order->getKey()])
        : null;
@endphp

@section('content')

  <p style="margin:0 0 20px; font-family:{{ $display }}; font-size:13px; font-weight:bold; letter-spacing:0.14em; text-transform:uppercase;">
    New order received
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
      <td style="border:1px solid {{ $hairline }}; padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.2em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:4px;">Order</td>
            <td align="right" style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.2em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:4px;">Total</td>
          </tr>
          <tr>
            <td style="font-family:{{ $display }}; font-size:18px; font-weight:bold; letter-spacing:0.1em; color:{{ $ink }};">{{ $order->order_no }}</td>
            <td align="right" style="font-family:{{ $display }}; font-size:18px; font-weight:bold; color:{{ $ink }};">{{ $order->currency }} {{ $order->grand_total }}</td>
          </tr>
          <tr>
            <td colspan="2" style="font-size:14px; color:{{ $muted }}; padding-top:8px;">
              {{ $order->payment_method->label() }} &middot; {{ $order->payment_status->label() }} &middot; placed {{ $order->placed_at?->format('j M Y, H:i') }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Customer -->
  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Customer</div>
  <p style="margin:0 0 22px; font-size:15px; line-height:1.8;">
    {{ $order->shipping_name }}<br>
    <a href="mailto:{{ $order->customer->email }}" style="color:#005DBA; text-decoration:none;">{{ $order->customer->email }}</a><br>
    <a href="tel:{{ $order->shipping_phone }}" style="color:#005DBA; text-decoration:none;">{{ $order->shipping_phone }}</a>
    @if ($previousOrders > 0)
      <br><span style="font-size:14px; color:{{ $muted }};">Repeat buyer — {{ $previousOrders }} previous order(s).</span>
    @endif
  </p>

  <!-- Ship to -->
  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Ship To</div>
  <p style="margin:0 0 22px; font-size:15px; line-height:1.8;">
    {{ $order->formattedAddress() }}
  </p>

  @if ($order->customer_note)
    <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Customer Note</div>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.8;">{{ $order->customer_note }}</p>
  @endif

  <!-- Items to pick -->
  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Items</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    @foreach ($order->items as $item)
      <tr>
        <td style="padding:10px 0; border-bottom:1px solid {{ $hairline }}; font-size:15px;">
          <strong style="font-family:{{ $display }}; font-size:12px; letter-spacing:0.06em; text-transform:uppercase;">{{ $item->sku_snapshot }}</strong><br>
          <span style="color:{{ $muted }};">{{ $item->name_snapshot }}</span>
        </td>
        <td align="right" valign="top" style="padding:10px 0; border-bottom:1px solid {{ $hairline }}; font-family:{{ $display }}; font-size:14px; font-weight:bold; white-space:nowrap;">
          &times; {{ $item->qty }}
        </td>
      </tr>
    @endforeach
  </table>

  @if ($adminUrl)
    <p style="margin:0;">
      <a href="{{ $adminUrl }}" style="display:inline-block; padding:14px 28px; background:{{ $ink }}; color:#FFFFFF; text-decoration:none; font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase;">Open in Dashboard</a>
    </p>
  @endif

@endsection
