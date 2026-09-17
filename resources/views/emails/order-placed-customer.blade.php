@extends('emails.layout')

@section('subject', 'Order '.$order->order_no.' confirmed — KOTIVA')

@section('preheader')
Your order {{ $order->order_no }} is confirmed. Pay {{ $order->currency }} {{ $order->grand_total }} on delivery.
@endsection

@php
    $accent = '#005DBA';
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
    $origin = rtrim((string) config('kotiva.site_origin'), '/');
@endphp

@section('content')

  <p style="margin:0 0 20px;">Hello {{ $order->customer->first_name }},</p>

  <p style="margin:0 0 20px;">
    Thank you for your order. We have received it and will be in touch when it ships.
  </p>

  <!-- Order number -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
    <tr>
      <td style="border:1px solid {{ $hairline }}; padding:18px 22px;">
        <div style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }};">Order Number</div>
        <div style="font-family:{{ $display }}; font-size:20px; font-weight:bold; letter-spacing:0.12em; color:{{ $ink }}; padding-top:6px;">{{ $order->order_no }}</div>
      </td>
    </tr>
  </table>

  <!-- Items. Every value is the order's own snapshot, never live product data:
       this email must keep reading correctly years after the catalog changes. -->
  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:12px;">Your Order</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
    @foreach ($order->items as $item)
      <tr>
        <td style="padding:12px 0; border-bottom:1px solid {{ $hairline }};">
          <div style="font-family:{{ $display }}; font-size:12px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:{{ $ink }};">{{ $item->name_snapshot }}</div>
          <div style="font-size:14px; color:{{ $muted }}; padding-top:4px;">{{ $item->sku_snapshot }} &middot; {{ $order->currency }} {{ $item->unit_price }} &times; {{ $item->qty }}</div>
        </td>
        <td align="right" valign="top" style="padding:12px 0; border-bottom:1px solid {{ $hairline }}; font-family:{{ $display }}; font-size:13px; font-weight:bold; color:{{ $ink }}; white-space:nowrap;">
          {{ $order->currency }} {{ $item->line_total }}
        </td>
      </tr>
    @endforeach
  </table>

  <!-- Totals -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
    <tr>
      <td style="padding:6px 0; font-size:15px; color:{{ $muted }};">Subtotal</td>
      <td align="right" style="padding:6px 0; font-size:15px; color:{{ $ink }};">{{ $order->currency }} {{ $order->subtotal }}</td>
    </tr>
    <tr>
      <td style="padding:6px 0; font-size:15px; color:{{ $muted }};">Shipping</td>
      <td align="right" style="padding:6px 0; font-size:15px; color:{{ $ink }};">
        {{ bccomp((string) $order->shipping_fee, '0.00', 2) === 0 ? 'Free' : $order->currency.' '.$order->shipping_fee }}
      </td>
    </tr>
    <tr>
      <td colspan="2" style="padding:6px 0 14px; font-size:13px; color:{{ $muted }};">
        Includes {{ $order->currency }} {{ $order->vat_amount }} VAT.
      </td>
    </tr>
    <tr>
      <td style="padding:14px 0 0; border-top:1px solid {{ $hairline }}; font-family:{{ $display }}; font-size:13px; font-weight:bold; letter-spacing:0.12em; text-transform:uppercase; color:{{ $ink }};">Total</td>
      <td align="right" style="padding:14px 0 0; border-top:1px solid {{ $hairline }}; font-family:{{ $display }}; font-size:16px; font-weight:bold; color:{{ $ink }};">{{ $order->currency }} {{ $order->grand_total }}</td>
    </tr>
  </table>

  <!-- Payment: the single most important line in this email for a COD order. -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
    <tr>
      <td style="border-left:3px solid {{ $accent }}; padding:14px 18px; background:rgba(138,183,233,0.10);">
        <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase; color:{{ $ink }};">{{ $order->payment_method->label() }}</div>
        <div style="font-size:15px; color:{{ $ink }}; padding-top:6px;">
          Please have <strong>{{ $order->currency }} {{ $order->grand_total }}</strong> ready for the courier.
        </div>
      </td>
    </tr>
  </table>

  <!-- Delivery -->
  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Delivering To</div>
  <p style="margin:0 0 20px; font-size:15px; line-height:1.8;">
    {{ $order->shipping_name }}<br>
    {{ $order->formattedAddress() }}<br>
    {{ $order->shipping_phone }}
  </p>

  @if ($deliveryEstimate)
    <p style="margin:0 0 24px; font-size:15px; color:{{ $muted }};">
      Estimated delivery: {{ $deliveryEstimate }}.
    </p>
  @endif

  <p style="margin:0;">
    <a href="{{ $origin }}/shop" style="display:inline-block; padding:14px 28px; background:{{ $ink }}; color:#FFFFFF; text-decoration:none; font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase;">Continue Shopping</a>
  </p>

@endsection
