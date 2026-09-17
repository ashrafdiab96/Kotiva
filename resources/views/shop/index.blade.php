@extends('layouts.app')

@section('meta')
  <title>Shop — kotiva™</title>
  <meta name="description" content="Browse the full kotiva™ collection — 25 doctor-approved skincare products formulated with clinically proven ingredients. Filter by concern, skin type or category." />
  <link rel="preload" as="image" href="{{ asset('assets/shop-hero-flatlay-v2.webp') }}" fetchpriority="high" />
  <link rel="canonical" href="{{ config('kotiva.site_origin') }}/shop">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Shop — kotiva™">
  <meta property="og:description" content="Browse the full kotiva™ collection — 25 doctor-approved skincare products formulated with clinically proven ingredients. Filter by concern, skin type or category.">
  <meta property="og:url" content="{{ config('kotiva.site_origin') }}/shop">
  <meta property="og:image" content="{{ config('kotiva.site_origin') }}/assets/shop-hero-flatlay-v2.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Shop — kotiva™">
  <meta name="twitter:description" content="Browse the full kotiva™ collection — 25 doctor-approved skincare products formulated with clinically proven ingredients.">
  <meta name="twitter:image" content="{{ config('kotiva.site_origin') }}/assets/shop-hero-flatlay-v2.webp">
@endsection

@section('content')
<!-- SHOP HERO — editorial -->
<section class="shop-hero" data-theme="editorial">
  <img src="{{ asset('assets/shop-hero-flatlay-v2.webp') }}" alt="" role="presentation" fetchpriority="high" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" />
  <div style="position:absolute;inset:0;background:linear-gradient(120deg,rgba(106,50,119,0.86) 0%,rgba(106,50,119,0.70) 45%,rgba(106,50,119,0.46) 100%);"></div>
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 30% 60%,rgba(106,50,119,0.12) 0%,transparent 55%);"></div>
  <div style="position:absolute;bottom:0;left:0;right:0;height:50%;background:linear-gradient(to top,rgba(106,50,119,0.6),transparent);"></div>
  <div class="shop-hero-content" style="position:relative;z-index:2;">
    <p class="t-eyebrow mb-16">Doctor-Approved Skincare</p>
    <h1 class="t-display t-display-lg" style="color:#FFFFFF;">The Collection</h1>
    <p style="font-family:var(--font-body);font-size:15px;color:#FFFFFF;margin-top:12px;">{{ $counts['all'] }} products formulated with clinically proven ingredients</p>
  </div>
</section>

<!-- FILTERS — sticky -->
<section data-theme="ritual" class="filter-sticky">
  <div class="container">
    <div class="shop-controls">
      <div class="filter-pills" data-target="#product-grid" role="group" aria-label="Filter products">
        @foreach ($filters as $tag => $label)
          <button class="filter-pill{{ $tag === 'all' ? ' active' : '' }}" data-filter="{{ $tag }}">{{ $label }} ({{ $counts[$tag] }})</button>
        @endforeach
      </div>

      {{-- Sort is server-side so it is linkable and works with JS off. Each
           option is a real link that carries the current filter through, which
           is what keeps pill state across a sort change. --}}
      <div class="shop-sort" role="group" aria-label="Sort products">
        <span class="shop-sort-label">Sort</span>
        @foreach ($sorts as $key => $label)
          <a class="filter-pill shop-sort-pill{{ $key === $activeSort ? ' active' : '' }}"
             href="{{ route('shop.index', array_filter(['sort' => $key === 'featured' ? null : $key, 'filter' => $activeFilter === 'all' ? null : $activeFilter])) }}"
             @if ($key === $activeSort) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
      </div>
    </div>
  </div>
</section>

<!-- PRODUCT GRID — ritual -->
<section data-theme="ritual" class="section-pad-sm">
  <div class="container">
    <div id="product-grid" data-stagger="40" style="display:grid;grid-template-columns:repeat(3,1fr);gap:2px;background:var(--border);">
{{-- The card is now a cell rather than a bare <a>: an add-to-cart form cannot
     legally nest inside an anchor, so the link and the form are siblings. The
     cell keeps the .product-card class and the data-tags attribute, which is
     what layout.js filters and counts on, so filtering and the grid-orphan
     rule are unaffected. --}}
@foreach ($products as $product)
      <div class="product-card{{ $product->isSoldOut() ? ' is-sold-out' : '' }}" data-tags="{{ implode(',', $product->filter_tags ?? []) }}" style="border:none;">
        <a class="product-card-link" href="{{ route('product.show', ['slug' => $product->slug]) }}">
          <div class="product-card-img">
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->displayName() }}" loading="lazy" />
            @if ($product->isSoldOut())
              <span class="product-card-flag">Sold out</span>
            @endif
          </div>
          <div class="product-card-body">
            <div class="product-card-name">{{ $product->displayName() }}</div>
            <div class="product-card-desc">{{ $product->category->name }} · {{ $product->skin_type }}</div>
            <div class="product-card-price">{{ config('kotiva.currency.code') }} {{ number_format((float) $product->price, 2) }}<small>VAT incl.</small></div>
            <div class="product-card-footer"><span class="badge-concern">{{ $product->concern }}</span><span class="product-card-cta">{{ $product->isSoldOut() ? 'Sold out' : 'Discover' }}</span></div>
          </div>
        </a>
        <form class="product-card-add" method="POST" action="{{ route('cart.items.store') }}" data-add-to-cart>
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->getKey() }}" />
          <input type="hidden" name="qty" value="1" />
          <button class="product-card-add-btn" type="submit" @disabled($product->isSoldOut())>
            {{ $product->isSoldOut() ? 'Sold out' : 'Add to cart' }}
          </button>
          <span class="stock-hint" data-stock-hint hidden></span>
        </form>
      </div>
@endforeach
    </div>
  </div>
</section>
@endsection

@push('scripts')
@verbatim
<script>
// Apply URL filter param on load
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const filter = params.get('filter');
  if (filter) {
    const pill = Array.from(document.querySelectorAll('.filter-pill[data-filter]')).find(p => p.dataset.filter === filter);
    if (pill) {
      pill.click();
      /* K32: the mobile pill row is a single horizontal scroller, so a pill deep in the row
         (Hair is last) is off-screen on arrival — the visitor lands on a filtered grid with
         no visible indication of WHICH filter is active. Bring it into view. `inline:'center'`
         also reveals the neighbouring pills, so the row reads as a row rather than a dead end.
         `block:'nearest'` keeps this from scrolling the PAGE, which would fight the deep-link. */
      if (pill.scrollIntoView) {
        pill.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' });
      }
    }
  }
});
</script>
@endverbatim
@endpush
