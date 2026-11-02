@extends('layouts.app')

@section('meta')
  <title>Terms of Use (Draft) — kotiva™</title>
  <meta name="description" content="The terms governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="{{ config('app.url') }}/terms">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Terms of Use (Draft) — kotiva™">
  <meta property="og:description" content="The terms governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta property="og:url" content="{{ config('app.url') }}/terms">
  <meta property="og:image" content="{{ config('app.url') }}/assets/hero-warm-skincare.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Terms of Use (Draft) — kotiva™">
  <meta name="twitter:description" content="The terms governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta name="twitter:image" content="{{ config('app.url') }}/assets/hero-warm-skincare.webp">
@endsection

@push('styles')
@verbatim
<style>
.legal-hero {
  padding-top: calc(var(--nav-h) + 80px);
  padding-bottom: 48px;
  border-bottom: 1px solid var(--border);
}
.draft-banner {
  background: var(--bg-alt);
  border: 1px solid var(--border-strong);
  padding: 20px 24px;
  margin-top: 28px;
  font-family: var(--font-body);
  font-size: 13px;
  line-height: 1.7;
  color: var(--fg-mid);
  max-width: 640px;
}
.draft-banner strong { color: var(--fg); }
.legal-content { padding: var(--gap-section) 0; }
.legal-content-inner { max-width: 760px; margin: 0 auto; }
.legal-content-inner h2 {
  font-family: var(--font-display);
  font-size: clamp(18px, 2vw, 24px);
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--fg);
  margin: 48px 0 16px;
}
.legal-content-inner h2:first-child { margin-top: 0; }
.legal-content-inner p, .legal-content-inner li {
  font-size: 15px;
  line-height: 1.8;
  color: var(--fg-mid);
  font-family: var(--font-body);
  margin-bottom: 16px;
}
.legal-content-inner ul { padding-left: 20px; margin-bottom: 16px; }
.legal-content-inner li { margin-bottom: 8px; }
.legal-content-inner a { color: var(--accent-deep); text-decoration: underline; }
.legal-meta {
  font-family: var(--font-display);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--fg-dim);
  margin-bottom: 32px;
}
</style>
@endverbatim
@endpush

@section('content')

  <header class="legal-hero">
    <div class="container">
      <p class="t-eyebrow reveal" style="margin-bottom:16px;">Legal</p>
      <h1 class="t-display reveal reveal-delay-1" style="margin-bottom:0;">TERMS<br>OF USE</h1>
      <div class="draft-banner reveal reveal-delay-2">
        <strong>Draft — pending legal review.</strong> This page is a template prepared for internal
        review and has not yet been approved as kotiva™'s final Terms of Use. Do not treat this
        as legally binding until it is reviewed and confirmed.
      </div>
    </div>
  </header>

  <section class="legal-content">
    <div class="container">
      <div class="legal-content-inner reveal">
        <p class="legal-meta">Last updated: October 2026 (draft)</p>

        <p>These Terms of Use apply to your use of the kotiva website and to orders you place through it.
        By using the website you agree to them, so please read them carefully. The website is operated by
        kotiva™, a brand of Vitakode LLC. If you do not agree with these terms, please do not use the
        website.</p>

        <h2>Information on This Website</h2>
        <p>The content on this website, including product descriptions, ingredient information, articles in
        the Journal and the results of the Routine Finder, is general information about skincare and
        haircare. It is not medical advice, and it does not replace a consultation with a doctor,
        dermatologist or pharmacist. If you have a skin condition, are pregnant or breastfeeding, or take
        prescription medication, please speak to a healthcare professional before using a new product. We
        work to keep the information accurate and up to date, but we cannot guarantee that it is free of
        errors, and results vary from person to person.</p>

        <h2>Buying From Us and From Retailers</h2>
        <p>kotiva products are available through selected pharmacies and retailers and through our website.
        Where a product is sold on our website, the price shown is in Saudi Riyals (SAR) and may change
        without notice. We accept an order when we confirm that it has been dispatched, and we may decline
        or cancel an order if a product is unavailable, if there is an error in the price or description,
        or if we suspect misuse. Delivery times shown at checkout are estimates, and delivery is available
        only to the countries listed at checkout. Products bought from a pharmacy or retailer are sold
        under that retailer's terms, together with our
        <a href="{{ route('returns') }}">Returns and Refunds</a> policy.</p>

        <h2>Using the Website Properly</h2>
        <p>You agree to use the website lawfully and not to interfere with it or its security, copy it
        automatically in bulk, upload harmful code, or give false details when you contact us or order. We
        may suspend access if these terms are broken.</p>

        <h2>Our Content and Brand</h2>
        <p>The kotiva name, logo, photography, text, designs and other content on this website belong to
        kotiva or its licensors and are protected by intellectual property laws. You may view and share
        the pages for personal, non-commercial use, but you may not copy, change or reuse our content or
        brand for any other purpose without our written permission.</p>

        <h2>Links to Other Websites</h2>
        <p>The website may link to other websites, such as retailers' pages. We do not control those sites
        and are not responsible for their content or policies.</p>

        <h2>Our Responsibility</h2>
        <p>We take care to provide the website and our products to a high standard, and nothing in these
        terms limits any right you have under the law that cannot be limited. Subject to that, we are not
        responsible for losses that were not reasonably foreseeable, for interruptions to the website
        beyond our control, or for loss caused by using the website or its information contrary to these
        terms or to a product's directions.</p>

        <h2>Changes, Law and Contact</h2>
        <p>We may update these terms from time to time. The version published on this website at the time
        you use it is the one that applies. These terms are governed by the laws of the Kingdom of Saudi
        Arabia, and any dispute will be handled by the competent courts of the Kingdom of Saudi Arabia,
        unless the law gives you the right to bring a claim elsewhere. If you have any question about these
        terms, please get in touch through our <a href="{{ route('contact') }}">Contact page</a>.</p>
      </div>
    </div>
  </section>

@endsection
