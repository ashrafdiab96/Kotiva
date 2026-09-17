@extends('emails.layout')

@section('subject', $headline.' — order '.$order->order_no)

@section('preheader'){{ $headline }}. Order {{ $order->order_no }}.@endsection

@php
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
    $origin = rtrim((string) config('kotiva.site_origin'), '/');
@endphp

@section('content')

  <p style="margin:0 0 20px;">Hello {{ $order->customer->first_name }},</p>

  {{-- $bodyCopy, not $message: Laravel injects its own $message into mail
       views and it would shadow this. --}}
  <p style="margin:0 0 24px; font-size:17px;">{{ $bodyCopy }}</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;">
    <tr>
      <td style="border:1px solid {{ $hairline }}; padding:16px 20px;">
        <div style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }};">Order</div>
        <div style="font-family:{{ $display }}; font-size:18px; font-weight:bold; letter-spacing:0.1em; color:{{ $ink }}; padding-top:5px;">{{ $order->order_no }}</div>
        <div style="font-size:14px; color:{{ $muted }}; padding-top:8px;">
          {{ $order->itemCount() }} item(s) &middot; {{ $order->currency }} {{ $order->grand_total }}
        </div>
      </td>
    </tr>
  </table>

  {{-- A cancelled order is the one case where money may need to move back, so
       it says plainly what happens next rather than leaving it implied. --}}
  @if ($order->status === \App\Enums\OrderStatus::Cancelled)
    <p style="margin:0 0 22px; font-size:15px; color:{{ $muted }};">
      Nothing has been charged — this order was cash on delivery, so there is no payment to refund.
      If you would still like these products, they are waiting for you in the shop.
    </p>
  @else
    <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Delivering To</div>
    <p style="margin:0 0 22px; font-size:15px; line-height:1.8;">
      {{ $order->shipping_name }}<br>
      {{ $order->formattedAddress() }}<br>
      {{ $order->shipping_phone }}
    </p>
  @endif

  <p style="margin:0;">
    <a href="{{ $origin }}/shop" style="display:inline-block; padding:14px 28px; background:{{ $ink }}; color:#FFFFFF; text-decoration:none; font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase;">Visit the Shop</a>
  </p>

@endsection
