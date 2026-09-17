@php
    /*
     | KOTIVA shared layout.
     |
     | The previous agency kept the header, mobile overlay and footer duplicated
     | across 36 static files, guarded by scripts/check-global-consistency.js.
     | This file replaces both: the markup is emitted once, so the blocks cannot
     | drift, and the consistency gate that policed the drift is retired.
     |
     | The markup below is the legacy markup verbatim, with two deliberate changes:
     |   1. hrefs/srcs go through route()/asset() — ingredients.html was the only
     |      page using root-absolute paths; routing normalises that divergence.
     |   2. .footer-social carries aria-label, not aria-hidden="true". index.html
     |      was the lone page using aria-hidden, which contradicted the
     //      role="img"+aria-label marks inside it. See docs/DECISIONS.md.
     */
    $v = config('app.asset_version');
@endphp
<!DOCTYPE html>
{{-- has-announcement retunes --nav-h so every existing calc(var(--nav-h) + …)
     offset in kotiva.css accounts for the bar without those rules changing. --}}
<html lang="en" data-theme="ritual"@if (! empty($announcementText ?? null)) class="has-announcement"@endif>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  @yield('meta')
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/favicon.svg') }}?v={{ $v }}">
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('assets/favicon-48.png') }}?v={{ $v }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/apple-touch-icon.png') }}?v={{ $v }}">
  <link rel="preload" href="{{ asset('fonts/FuturaNowVar-Roman.woff2') }}?v={{ $v }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('fonts/Montserrat-VariableFont_wght_25.woff2') }}?v={{ $v }}" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/kotiva.css') }}?v={{ $v }}" />
  {{-- E-commerce surfaces only (sort control, stock states, cart, mini-cart,
       checkout). Loaded after kotiva.css so it extends rather than overrides,
       and it introduces no new colour or type values of its own. --}}
  <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ $v }}" />
  @stack('styles')
</head>
<body>
<!-- ═══════════════════════════════════════
     NAVIGATION
═══════════════════════════════════════ -->
<a class="skip-link" href="#main-content">Skip to content</a>
@if (! empty($announcementText ?? null))
  {{-- Above the nav (§7.6). After the skip link, which must stay the first
       focusable element on the page. role="status" rather than "alert": this
       is standing information, not something that just happened, so it should
       not interrupt a screen reader mid-sentence. --}}
  <div class="kotiva-announce" role="status">
    <p class="kotiva-announce-text">{{ $announcementText }}</p>
  </div>
@endif
<nav class="nav transparent" id="main-nav" role="navigation">
  <div class="nav-inner">
    <a class="nav-logo" href="{{ route('home') }}" aria-label="KOTIVA Home">
      <img class="logo-dark" src="{{ asset('assets/kotiva-logo.svg') }}?v={{ $v }}" alt="KOTIVA" height="28" />
      <img class="logo-light" src="{{ asset('assets/kotiva-logo-white.svg') }}?v={{ $v }}" alt="KOTIVA" height="28" style="display:none;" />
    </a>
    <div class="nav-links" role="list">
      <a class="nav-link" href="{{ route('home') }}" role="listitem">Home</a>
      <a class="nav-link" href="{{ route('shop.index') }}" role="listitem">Shop</a>
      <a class="nav-link" href="{{ route('science') }}" role="listitem">Science</a>
      <a class="nav-link" href="{{ route('journal') }}" role="listitem">Journal</a>
      <a class="nav-link" href="{{ route('about') }}" role="listitem">About</a>
      <a class="nav-link" href="{{ route('contact') }}" role="listitem">Where to Buy</a>
    </div>
    <div class="nav-right">
      <a class="btn btn-gold btn-sm" href="{{ route('routine-finder') }}">Find My Routine</a>
      {{-- Cart control. A real link to /cart, so it works with JS off; shop.js
           upgrades it to open the mini-cart drawer in place. The badge is
           filled from /cart/summary on load, never rendered server-side, so a
           cached page can never show a stale count. --}}
      <a class="nav-cart" href="{{ route('cart.index') }}" data-mini-cart-open aria-label="Open cart">
        <span class="nav-cart-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </span>
        <span class="nav-cart-badge is-empty" data-cart-badge aria-live="polite"></span>
      </a>
      <button class="nav-theme-btn" id="theme-toggle" aria-label="Switch to dark mode" title="Toggle light/dark">
        <span class="icon-moon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg></span>
        <span class="icon-sun" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
      </button>
      <button class="nav-hamburger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- Mobile Overlay -->
<div class="nav-overlay" role="dialog" aria-modal="true" aria-label="Navigation menu">
  <a class="nav-link" href="{{ route('home') }}">Home</a>
  <a class="nav-link" href="{{ route('shop.index') }}">Shop</a>
  <a class="nav-link" href="{{ route('science') }}">Science</a>
  <a class="nav-link" href="{{ route('journal') }}">Journal</a>
  <a class="nav-link" href="{{ route('about') }}">About</a>
  <a class="nav-link" href="{{ route('contact') }}">Where to Buy</a>
  <a class="btn btn-gold" href="{{ route('routine-finder') }}">Find My Routine</a>
</div>

<main id="main-content"@hasSection('main_attrs') @yield('main_attrs')@endif>
@yield('content')
</main>

<!-- ═══════════════════════════════════════
     FOOTER — ritual
═══════════════════════════════════════ -->
<footer class="footer" data-theme="ritual" role="contentinfo">
  <div class="container">
    <div class="footer-main">
      <div>
        <a class="footer-brand-link" href="{{ route('home') }}" aria-label="KOTIVA — home"><img class="footer-brand-logo logo-dark" src="{{ asset('assets/kotiva-logo.svg') }}?v={{ $v }}" alt="" /></a>
        <p class="footer-tagline mt-16">Doctor-approved skincare. Perfected by science. Tailored for you.</p>
        <!-- SOCIAL: accounts not created yet. Rendered as non-interactive marks on purpose -->
        <!-- (no href = no dead click). To activate: swap each span for -->
        <!-- <a href="URL" target="_blank" rel="noopener noreferrer" aria-label="X"> and drop the caption. -->
        <div class="footer-social" aria-label="Social media — accounts coming soon">
          <span class="footer-social-icon" role="img" aria-label="Instagram"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="currentColor"/></svg></span>
          <span class="footer-social-icon" role="img" aria-label="TikTok"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.16 8.16 0 004.77 1.52V6.75a4.85 4.85 0 01-1-.06z"/></svg></span>
          <span class="footer-social-icon" role="img" aria-label="Facebook"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></span>
        </div>
        <span class="footer-social-soon">Coming soon</span>
      </div>
      <div><div class="footer-col-title">Shop</div>
        <nav class="footer-links" aria-label="Shop links">
          <a href="{{ route('shop.index') }}">All Products</a><a href="{{ route('shop.index', ['filter' => 'face']) }}">Face Care</a>
          <a href="{{ route('shop.index', ['filter' => 'body']) }}">Body Care</a><a href="{{ route('shop.index', ['filter' => 'serums']) }}">Serums</a>
          <a href="{{ route('shop.index', ['filter' => 'sunscreen']) }}">SPF</a><a href="{{ route('shop.index', ['filter' => 'hair']) }}">Hair</a>
        </nav>
      </div>
      <div><div class="footer-col-title">Company</div>
        <nav class="footer-links" aria-label="Company links">
          <a href="{{ route('about') }}">About Kotiva</a><a href="{{ route('science') }}">Our Science</a><a href="{{ route('ingredients') }}">Ingredient Glossary</a>
          <a href="{{ route('routine-finder') }}">Routine Finder</a><a href="{{ route('journal') }}">Journal</a><a href="{{ route('home') }}#newsletter">Newsletter</a>
          <a href="{{ route('contact') }}">Contact Us</a><a href="{{ asset('assets/kotiva-company-profile.pdf') }}" target="_blank" rel="noopener">Company Profile (PDF)</a>
        </nav>
      </div>
      <div><div class="footer-col-title">Support</div>
        <nav class="footer-links" aria-label="Support links">
          <a href="{{ route('privacy-policy') }}">Privacy Policy</a><a href="{{ route('terms') }}">Terms of Use</a><a href="{{ route('contact') }}#faq">FAQ</a>
        </nav>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; 2026 KOTIVA&trade;, a brand of Vitakode LLC. All rights reserved.</span>
      <span>Doctor-approved skincare tailored for you.</span>
    </div>
  </div>
</footer>

<!-- ═══════════════════════════════════════
     MINI-CART DRAWER
     Opened by the nav cart control and after an add-to-cart.
     Starts hidden and empty; shop.js fills it from the server's
     own totals. aria-modal + focus trap mirror .nav-overlay.
═══════════════════════════════════════ -->
<div class="mini-cart is-empty" role="dialog" aria-modal="true" aria-label="Your cart" aria-hidden="true">
  <div class="mini-cart-scrim" data-mini-cart-close></div>
  <aside class="mini-cart-panel">
    <header class="mini-cart-head">
      <p class="mini-cart-title">Your Cart</p>
      <button class="mini-cart-close" type="button" data-mini-cart-close aria-label="Close cart">&times;</button>
    </header>

    <p class="mini-cart-empty" data-mini-cart-empty>Your cart is empty.</p>

    <ul class="mini-cart-items" data-mini-cart-items role="list"></ul>

    <footer class="mini-cart-foot">
      <div class="mini-cart-subtotal-row">
        <span>Subtotal</span>
        <span data-mini-cart-subtotal>{{ config('kotiva.currency.code') }} 0.00</span>
      </div>
      <p class="mini-cart-note">Shipping calculated at checkout. All prices include VAT.</p>
      <a class="btn btn-outline mini-cart-view" href="{{ route('cart.index') }}">View Cart</a>
      <a class="btn btn-primary mini-cart-checkout" href="{{ route('checkout.index') }}">Checkout</a>
    </footer>
  </aside>
</div>

@stack('pre_scripts')
<script src="{{ asset('js/layout.js') }}?v={{ $v }}"></script>
<script src="{{ asset('js/animations.js') }}?v={{ $v }}"></script>
<script src="{{ asset('js/shop.js') }}?v={{ $v }}"></script>
@stack('scripts')
</body>
</html>
