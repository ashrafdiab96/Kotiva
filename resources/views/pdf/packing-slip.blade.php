{{--
  KOTIVA packing slip (§7.4), rendered by dompdf.

  Standalone rather than extending emails.layout: that shell is built for email
  clients and carries a wordmark link, a "Continue Shopping" button and a
  contact footer, none of which belong on a document that goes in a box. The
  brand tokens are shared with it deliberately so the two read as one brand.

  Fonts are dompdf's built-ins. The self-hosted brand faces cannot be embedded
  here any more than they can in email, and naming a font dompdf does not have
  silently falls back to its default — so Helvetica/Times are named explicitly
  to approximate the display/body pairing rather than leaving it to chance.

  Every figure is the order's own snapshot. A slip reprinted a year later must
  match what the customer was charged, not what the catalog says today.
--}}
@php
    $accent = '#005DBA';
    $ink = '#231F20';
    $muted = '#6B6769';
    $hairline = '#DCDADB';
    $display = "Helvetica, Arial, sans-serif";
    $body = "Times, 'Times New Roman', serif";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Packing slip {{ $order->order_no }}</title>
  <style>
    @page { margin: 18mm 16mm; }
    body { margin: 0; font-family: {{ $body }}; color: {{ $ink }}; font-size: 11pt; }
    table { width: 100%; border-collapse: collapse; }
    .eyebrow { font-family: {{ $display }}; font-size: 7pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; color: {{ $muted }}; }
    .wordmark { font-family: {{ $display }}; font-size: 17pt; font-weight: bold; letter-spacing: 4pt; text-transform: uppercase; color: {{ $ink }}; }
    .doc-title { font-family: {{ $display }}; font-size: 11pt; font-weight: bold; letter-spacing: 2pt; text-transform: uppercase; color: {{ $muted }}; }
    .rule { border-top: 2pt solid {{ $accent }}; font-size: 0; line-height: 0; height: 0; }
    .order-no { font-family: {{ $display }}; font-size: 16pt; font-weight: bold; letter-spacing: 1.2pt; color: {{ $ink }}; }
    .label { font-family: {{ $display }}; font-size: 7.5pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; color: {{ $muted }}; }
    .item-name { font-family: {{ $display }}; font-size: 9.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; }
    .meta { font-size: 9.5pt; color: {{ $muted }}; }
    .cell { padding: 7pt 0; border-bottom: 0.5pt solid {{ $hairline }}; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .total-row td { padding-top: 9pt; border-top: 0.75pt solid {{ $ink }}; font-family: {{ $display }}; font-weight: bold; }
    .cod { border-left: 3pt solid {{ $accent }}; background: #EEF4FB; padding: 10pt 12pt; }
    .cod-amount { font-family: {{ $display }}; font-size: 15pt; font-weight: bold; color: {{ $ink }}; }
    .foot { font-family: {{ $display }}; font-size: 7pt; letter-spacing: 1.2pt; text-transform: uppercase; color: {{ $muted }}; }
  </style>
</head>
<body>

  <table>
    <tr>
      <td>
        <div class="wordmark">kotiva</div>
        <div class="eyebrow" style="padding-top:3pt;">Doctor-Approved Skincare</div>
      </td>
      <td class="num">
        <div class="doc-title">Packing Slip</div>
        <div class="meta" style="padding-top:4pt;">
          {{ optional($order->placed_at)->format('j M Y') ?? $order->created_at->format('j M Y') }}
        </div>
      </td>
    </tr>
  </table>

  <table style="margin-top:10pt;"><tr><td class="rule">&nbsp;</td></tr></table>

  {{-- Order identity and destination, side by side. --}}
  <table style="margin-top:16pt;">
    <tr>
      <td style="width:50%; vertical-align:top; padding-right:12pt;">
        <div class="label">Order Number</div>
        <div class="order-no" style="padding-top:4pt;">{{ $order->order_no }}</div>
        <div class="meta" style="padding-top:8pt;">
          {{ $order->payment_method->label() }} &middot; {{ $order->payment_status->label() }}<br>
          Status: {{ $order->status->label() }}
        </div>
      </td>
      <td style="width:50%; vertical-align:top;">
        <div class="label">Deliver To</div>
        <div style="padding-top:5pt; font-size:11pt; line-height:1.55;">
          <strong>{{ $order->shipping_name }}</strong><br>
          {{ $order->formattedAddress() }}<br>
          {{ $order->shipping_phone }}
        </div>
      </td>
    </tr>
  </table>

  {{-- Items. Snapshots only. --}}
  <div class="label" style="padding:20pt 0 6pt;">Items &middot; {{ $order->itemCount() }} {{ $order->itemCount() === 1 ? 'unit' : 'units' }}</div>

  <table>
    <tr>
      <td class="cell label" style="border-bottom-width:0.75pt;">Product</td>
      <td class="cell label num" style="border-bottom-width:0.75pt; width:12%;">Qty</td>
      <td class="cell label num" style="border-bottom-width:0.75pt; width:20%;">Unit</td>
      <td class="cell label num" style="border-bottom-width:0.75pt; width:20%;">Total</td>
    </tr>
    @foreach ($order->items as $item)
      <tr>
        <td class="cell">
          <div class="item-name">{{ $item->name_snapshot }}</div>
          <div class="meta" style="padding-top:2pt;">{{ $item->sku_snapshot }}</div>
        </td>
        <td class="cell num" style="font-family:{{ $display }}; font-weight:bold;">{{ $item->qty }}</td>
        <td class="cell num meta">{{ $order->currency }} {{ $item->unit_price }}</td>
        <td class="cell num" style="font-family:{{ $display }}; font-weight:bold;">{{ $order->currency }} {{ $item->line_total }}</td>
      </tr>
    @endforeach
  </table>

  {{-- Totals, right-aligned in a narrow column. --}}
  <table style="margin-top:14pt;">
    <tr>
      <td style="width:55%;">&nbsp;</td>
      <td>
        <table>
          <tr>
            <td class="meta" style="padding:3pt 0;">Subtotal</td>
            <td class="num" style="padding:3pt 0;">{{ $order->currency }} {{ $order->subtotal }}</td>
          </tr>
          <tr>
            <td class="meta" style="padding:3pt 0;">Shipping</td>
            <td class="num" style="padding:3pt 0;">
              {{ bccomp((string) $order->shipping_fee, '0.00', 2) === 0 ? 'Free' : $order->currency.' '.$order->shipping_fee }}
            </td>
          </tr>
          @if (bccomp((string) $order->discount_total, '0.00', 2) !== 0)
            <tr>
              <td class="meta" style="padding:3pt 0;">Discount</td>
              <td class="num" style="padding:3pt 0;">&minus; {{ $order->currency }} {{ $order->discount_total }}</td>
            </tr>
          @endif
          <tr class="total-row">
            <td style="font-size:9.5pt; letter-spacing:1.2pt; text-transform:uppercase;">Total</td>
            <td class="num" style="font-size:12pt;">{{ $order->currency }} {{ $order->grand_total }}</td>
          </tr>
          <tr>
            <td colspan="2" class="meta" style="padding-top:5pt; font-size:9pt;">
              Includes {{ $order->currency }} {{ $order->vat_amount }} VAT. Prices are VAT-inclusive.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- The one line the courier actually acts on. Printed only while the cash
       is still outstanding, so a paid order's slip cannot prompt a second
       collection. --}}
  @if ($order->payment_status !== \App\Enums\PaymentStatus::Paid)
    <table style="margin-top:18pt;">
      <tr>
        <td class="cod">
          <div class="label" style="color:{{ $ink }};">Collect on Delivery</div>
          <div class="cod-amount" style="padding-top:4pt;">{{ $order->currency }} {{ $order->grand_total }}</div>
        </td>
      </tr>
    </table>
  @endif

  @if ($order->customer_note)
    <div class="label" style="padding:18pt 0 5pt;">Customer Note</div>
    <div style="font-size:10.5pt; line-height:1.6;">{{ $order->customer_note }}</div>
  @endif

  <table style="margin-top:26pt;">
    <tr><td style="border-top:0.5pt solid {{ $hairline }}; padding-top:8pt;">
      <div class="foot">KOTIVA&trade;, a brand of Vitakode LLC &middot; Order {{ $order->order_no }}</div>
    </td></tr>
  </table>

</body>
</html>
