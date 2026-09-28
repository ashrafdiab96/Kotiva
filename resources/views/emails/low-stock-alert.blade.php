@extends('emails.layout')

@section('subject', 'Low stock: '.$product->displayName())

@section('preheader')
{{ $product->sku }} — {{ $remaining }} left of {{ $product->displayName() }}.
@endsection

@php
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $warn = '#EC7725';
    $out = '#D7282F';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
    $origin = rtrim((string) config('kotiva.site_origin'), '/');
    $isOut = $remaining <= 0;
@endphp

@section('content')

  <p style="margin:0 0 20px; font-family:{{ $display }}; font-size:13px; font-weight:bold; letter-spacing:0.14em; text-transform:uppercase; color:{{ $isOut ? $out : $warn }};">
    {{ $isOut ? 'Product sold out' : 'Stock running low' }}
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
      <td style="border-left:3px solid {{ $isOut ? $out : $warn }}; padding:16px 20px; background:rgba(236,119,37,0.07);">
        <div style="font-family:{{ $display }}; font-size:14px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:{{ $ink }};">
          {{ $product->displayName() }}
        </div>
        <div style="font-size:14px; color:{{ $muted }}; padding-top:6px;">
          {{ $product->sku }}@if ($product->volume) &middot; {{ $product->volume }}@endif
        </div>
        <div style="font-family:{{ $display }}; font-size:26px; font-weight:bold; color:{{ $isOut ? $out : $ink }}; padding-top:12px;">
          {{ $remaining }}
        </div>
        <div style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.2em; text-transform:uppercase; color:{{ $muted }};">
          {{ $isOut ? 'units remaining' : 'units remaining — threshold '.$product->low_stock_threshold }}
        </div>
      </td>
    </tr>
  </table>

  <p style="margin:0 0 20px; font-size:15px; line-height:1.8;">
    @if ($isOut)
      This product is now showing as sold out on the storefront. It stays listed and browsable,
      with its Add to Cart disabled, so restocking it brings it straight back.
    @else
      The storefront is now showing "Only {{ $remaining }} left" on this product's page.
      It will show as sold out once it reaches zero.
    @endif
  </p>

  <p style="margin:0 0 22px; font-size:14px; color:{{ $muted }};">
    You will not receive another alert for this product for
    {{ (int) config('kotiva.stock.low_stock_alert_debounce_hours') }} hours.
  </p>

  <p style="margin:0;">
    <a href="{{ $origin }}/product/{{ $product->slug }}" style="display:inline-block; padding:14px 28px; background:{{ $ink }}; color:#FFFFFF; text-decoration:none; font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase;">View Product</a>
  </p>

@endsection
