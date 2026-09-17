@extends('layouts.app')

@php
    $origin = config('kotiva.site_origin');
    $pageUrl = $origin.'/product/'.$product->slug;
    $absImg = $origin.'/'.ltrim((string) $product->imagePath(), '/');
    $title = $product->name.' — kotiva™';
    $desc = (string) $product->meta_description;

    /*
     | Product JSON-LD. Identical in shape to what the retired generator
     | emitted, with one deliberate correction: `availability` was hardcoded to
     | PreOrder on every page because the static site could not know stock.
     | It now reflects the real quantity, which is the whole point of having a
     | catalog behind the page.
     */
    $productLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->description,
        'image' => $absImg,
        'url' => $pageUrl,
        'sku' => $product->sku,
        'brand' => ['@type' => 'Brand', 'name' => 'KOTIVA'],
        'category' => $product->category->name,
        'offers' => [
            '@type' => 'Offer',
            'url' => $pageUrl,
            'availability' => $product->schemaAvailability(),
            'itemCondition' => 'https://schema.org/NewCondition',
            'price' => number_format((float) $product->price, 2, '.', ''),
            'priceCurrency' => config('kotiva.currency.code'),
            'valueAddedTaxIncluded' => true,
        ],
    ];

    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Products', 'item' => $origin.'/shop'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $zoneCrumb['name'], 'item' => $origin.'/shop?filter='.urlencode($zoneCrumb['filter'])],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product->name, 'item' => $pageUrl],
        ],
    ];
@endphp

@section('meta')
  <title>{{ $title }}</title>
  <meta name="description" content="{{ $desc }}">
  <link rel="canonical" href="{{ $pageUrl }}">
  <meta property="og:type" content="product">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="{{ $title }}">
  <meta property="og:description" content="{{ $desc }}">
  <meta property="og:url" content="{{ $pageUrl }}">
  <meta property="og:image" content="{{ $absImg }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $title }}">
  <meta name="twitter:description" content="{{ $desc }}">
  <meta name="twitter:image" content="{{ $absImg }}">
  <script type="application/ld+json">{!! json_encode($productLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
  <script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('styles')
@verbatim
<style>
.pdp-hero { padding-top: var(--nav-h); display: grid; grid-template-columns: 1fr 1fr; min-height: 88vh; }
.pdp-gallery { position: sticky; top: var(--nav-h); height: calc(100vh - var(--nav-h)); overflow: hidden; background: #231F20; display: flex; align-items: center; justify-content: center; }
.pdp-gallery-img { width: 70%; max-width: 420px; aspect-ratio: 3/4; object-fit: cover; }
.pdp-gallery-badge { position: absolute; top: 32px; left: 32px; font-family: var(--font-display); font-size: 8px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; background: var(--bronze); color: var(--black); padding: 6px 14px; }
/* K12: dim gallery number on the #231F20 gallery — alpha from the approved palette (docs/COLOUR-MIGRATION.md). */
.pdp-gallery-num { position: absolute; bottom: 32px; right: 32px; font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.2em; color: rgba(255,255,255,0.64); }
.pdp-content { padding: clamp(48px, 6vw, 80px) clamp(36px, 5vw, 72px); border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 0; }
.pdp-breadcrumb { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--fg-dim); margin-bottom: 28px; display: flex; gap: 12px; align-items: center; }
.pdp-breadcrumb a { color: inherit; transition: color 0.2s; }
.pdp-breadcrumb a:hover { color: var(--accent-text); }
.pdp-breadcrumb-sep { color: var(--border); }
.pdp-skin-type { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--accent-deep); margin-bottom: 16px; }
.pdp-name { font-family: var(--font-display); font-size: clamp(28px, 3vw, 48px); font-weight: 900; letter-spacing: 0.03em; text-transform: uppercase; line-height: 1.0; color: var(--fg); margin-bottom: 8px; }
.pdp-category { font-family: var(--font-display); font-size: 10px; font-weight: 600; letter-spacing: 0.2em; text-transform: uppercase; color: var(--fg-dim); margin-bottom: 16px; }
.pdp-price { font-family: var(--font-display); font-size: 20px; font-weight: 700; letter-spacing: 0.04em; color: var(--fg); margin-bottom: 28px; display: flex; align-items: baseline; gap: 10px; }
.pdp-price-vat { font-size: 9px; font-weight: 600; letter-spacing: 0.18em; text-transform: uppercase; color: var(--fg-dim); }
.pdp-claim { font-family: var(--font-script); font-size: clamp(18px, 1.8vw, 28px); color: var(--script); margin-bottom: 28px; }
.pdp-divider { height: 1px; background: var(--border); margin: 28px 0; }
.pdp-description { font-size: 15px; line-height: 1.8; color: var(--fg-mid); font-family: var(--font-body); margin-bottom: 32px; }
.pdp-benefits-list { list-style: none; margin: 0 0 28px; padding: 0; display: grid; gap: 10px; }
.pdp-benefits-list li { position: relative; padding-left: 20px; font-size: 14px; line-height: 1.6; color: var(--fg-mid); font-family: var(--font-body); }
.pdp-benefits-list li::before { content: ''; position: absolute; left: 0; top: 8px; width: 6px; height: 6px; background: var(--bronze); border-radius: 50%; }
.pdp-ingredients-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
.pdp-ingredient-pill { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; padding: 6px 14px; border: 1px solid var(--border); color: var(--accent-deep); }
.pdp-free-from { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
.pdp-free-pill { font-family: var(--font-display); font-size: 8px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; padding: 5px 12px; border: 1px solid rgba(0,69,57,0.2); color: rgba(0,69,57,0.6); }
.pdp-how-to { background: var(--bg-card); border: 1px solid var(--border); padding: 24px 28px; margin-bottom: 32px; }
.pdp-how-label { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--accent-deep); margin-bottom: 12px; }
.pdp-how-text { font-size: 14px; line-height: 1.7; color: var(--fg-mid); font-family: var(--font-body); }
.pdp-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.pdp-doctor-badge { margin-top: 28px; display: flex; align-items: center; gap: 16px; border: 1px solid var(--border); padding: 16px 20px; }
.pdp-doctor-icon { font-size: 22px; flex-shrink: 0; }
.pdp-doctor-text { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--fg-mid); line-height: 1.7; }
.related-section { padding: var(--gap-section) 0; border-top: 1px solid var(--border); }
.related-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px; margin-top: clamp(36px, 4vw, 56px); }
@media (max-width: 1000px) {
  .pdp-hero { grid-template-columns: 1fr; }
  .pdp-gallery { position: relative; height: 60vw; min-height: 300px; top: 0; }
  .pdp-content { border-left: none; border-top: 1px solid var(--border); }
  .related-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
@endverbatim
@endpush

@push('styles')
@verbatim
<style>
/* Stock state — new for the shop, built on the existing PDP type scale. */
.pdp-stock { display: flex; align-items: center; gap: 9px; margin-bottom: 18px; font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; }
.pdp-stock-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.pdp-stock.is-in .pdp-stock-dot { background: #004539; }
.pdp-stock.is-low .pdp-stock-dot { background: #EC7725; }
.pdp-stock.is-out .pdp-stock-dot { background: var(--fg-dim); }
.pdp-stock.is-in { color: #004539; }
.pdp-stock.is-low { color: #EC7725; }
.pdp-stock.is-out { color: var(--fg-dim); }
.pdp-cod-note { font-family: var(--font-body); font-size: 13px; line-height: 1.6; color: var(--fg-dim); margin-top: 14px; }
html[data-mode="dark"] .pdp-stock.is-in { color: #8AB7E9; }
html[data-mode="dark"] .pdp-stock.is-in .pdp-stock-dot { background: #8AB7E9; }
html[data-mode="dark"] .pdp-stock.is-out { color: rgba(255,255,255,0.62); }
html[data-mode="dark"] .pdp-stock.is-out .pdp-stock-dot { background: rgba(255,255,255,0.62); }
</style>
@endverbatim
@endpush

@section('content')
  <div class="pdp-hero">
    <div class="pdp-gallery">
      <div class="grain-overlay"></div>
      <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="pdp-gallery-img">
      <div class="pdp-gallery-num">{{ str_pad((string) $position, 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $catalogTotal, 2, '0', STR_PAD_LEFT) }}</div>
    </div>

    <div class="pdp-content">
      <div class="pdp-breadcrumb">
        <a href="{{ route('shop.index') }}">Products</a>
        <span class="pdp-breadcrumb-sep">&rarr;</span>
        <span>{{ $product->category->name }}</span>
      </div>

      <div class="pdp-skin-type">{{ $product->skin_type }}</div>
      <h1 class="pdp-name">{{ $product->name }}</h1>
      <div class="pdp-category">{{ $product->category->name }}@if ($product->volume) &middot; {{ $product->volume }}@endif</div>
      <div class="pdp-price"><span>{{ config('kotiva.currency.code') }} {{ number_format((float) $product->price, 2) }}</span><span class="pdp-price-vat">VAT included</span></div>

      @if ($product->isSoldOut())
        <div class="pdp-stock is-out"><span class="pdp-stock-dot"></span>Sold out</div>
      @elseif ($product->isLowStock())
        <div class="pdp-stock is-low"><span class="pdp-stock-dot"></span>Only {{ $product->stock_qty }} left</div>
      @else
        <div class="pdp-stock is-in"><span class="pdp-stock-dot"></span>In stock</div>
      @endif

      <div class="pdp-claim">{{ $product->action }}</div>

      <div class="pdp-divider"></div>

      <p class="pdp-description">{{ $product->description }}</p>

      @if ($facts !== [])
      <div class="pdp-facts" aria-label="Product at a glance">
        @foreach ($facts as $fact)<div class="pdp-fact">{!! $factIcons->icon($fact['key']) !!}<div><div class="pdp-fact-label">{{ $fact['label'] }}</div><div class="pdp-fact-value">{{ $fact['value'] }}</div></div></div>@endforeach
      </div>
      @endif

      @if ($product->benefits)
      <div class="pdp-benefits" style="margin-bottom:8px;">
        <p class="t-eyebrow" style="margin-bottom:12px; color:var(--fg-dim);">Key Benefits</p>
        <ul class="pdp-benefits-list">@foreach ($product->benefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
      </div>
      @endif

      @if ($product->ingredients)
      <div style="margin-bottom:8px;">
        <p class="t-eyebrow" style="margin-bottom:12px; color:var(--fg-dim);">Key Ingredients</p>
        <div class="pdp-ingredients-list">@foreach ($product->ingredients as $ingredient)<span class="pdp-ingredient-pill">{{ $ingredient }}</span>@endforeach</div>
      </div>
      @endif

      @if ($product->free_from)
      <div class="pdp-free-from">@foreach ($product->free_from as $free)<span class="pdp-free-pill">{{ $free }} Free</span>@endforeach</div>
      @endif

      <div class="pdp-how-to">
        <div class="pdp-how-label">How to Use</div>
        <p class="pdp-how-text">{{ $product->how_to_use ?: 'Apply as directed. Part of The Kotiva Standard routine.' }}</p>
      </div>

      @if ($scienceHtml !== '')
      <details class="pdp-science">
        <summary><span>The Science</span><span class="pdp-science-hint">Actives &amp; clinical references</span></summary>
        <div class="pdp-science-body">{!! $scienceHtml !!}</div>
      </details>
      @endif

      {{-- Buy box. A real form posting to /cart/items, so it works with JS
           off (POST then redirect back); shop.js upgrades it to add in place
           and open the mini-cart. Replaces the old "Where to Buy" CTA. --}}
      <form class="pdp-buy" method="POST" action="{{ route('cart.items.store') }}" data-add-to-cart>
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->getKey() }}" />

        @if ($product->isSoldOut())
          <button class="btn btn-primary pdp-add" type="submit" disabled>Sold out</button>
        @else
          <div class="pdp-qty">
            <label class="visually-hidden" for="pdp-qty">Quantity</label>
            <select class="pdp-qty-select" id="pdp-qty" name="qty">
              @for ($i = 1; $i <= $product->maxOrderableQty(); $i++)
                <option value="{{ $i }}">{{ $i }}</option>
              @endfor
            </select>
          </div>
          <button class="btn btn-primary pdp-add" type="submit">Add to Cart</button>
        @endif

        <span class="stock-hint" data-stock-hint hidden></span>
      </form>

      <div class="pdp-actions">
        <a href="{{ route('routine-finder') }}" class="btn btn-outline">Build Your Routine</a>
      </div>
      <p class="pdp-cod-note">Cash on delivery available.</p>

      <div class="pdp-doctor-badge">
        <div class="pdp-doctor-icon">&#9877;</div>
        <div class="pdp-doctor-text">
          Doctor Approved &middot; Kotiva Standard<br>
          Formulated with clinically proven active ingredients
        </div>
      </div>
    </div>
  </div>

  @if ($related->isNotEmpty())
  <section class="related-section">
    <div class="container">
      <div class="section-header-row">
        <div>
          <p class="t-eyebrow" style="margin-bottom:12px;">You May Also Need</p>
          <h2 class="t-title">COMPLETE YOUR<br>ROUTINE</h2>
        </div>
        <a href="{{ route('shop.index') }}" class="btn-text-light">View All &rarr;</a>
      </div>
      <div class="related-grid">
@foreach ($related as $rel)
        <a class="product-card{{ $rel->isSoldOut() ? ' is-sold-out' : '' }}" href="{{ route('product.show', ['slug' => $rel->slug]) }}" style="border:none;">
          <div class="product-card-img">
            <img src="{{ $rel->imageUrl() }}" alt="{{ $rel->displayName() }}" loading="lazy">
            @if ($rel->isSoldOut())<span class="product-card-flag">Sold out</span>@endif
          </div>
          <div class="product-card-body">
            <div class="product-card-name">{{ $rel->displayName() }}</div>
            <div class="product-card-desc">{{ $rel->category->name }} &middot; {{ $rel->skin_type }}</div>
            <div class="product-card-footer"><span class="badge-concern">{{ $rel->concern }}</span><span class="product-card-cta">{{ $rel->isSoldOut() ? 'Sold out' : 'Discover' }}</span></div>
          </div>
        </a>
@endforeach
      </div>
    </div>
  </section>
  @endif
@endsection
