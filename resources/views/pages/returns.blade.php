@extends('layouts.app')

@section('meta')
  <title>Returns &amp; Refunds (Draft) — kotiva™</title>
  <meta name="description" content="The kotiva™ (Vitakode LLC) returns and refunds policy, and how to report a faulty or damaged product. Draft template pending legal review.">
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="{{ config('app.url') }}/returns">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Returns &amp; Refunds (Draft) — kotiva™">
  <meta property="og:description" content="The kotiva™ (Vitakode LLC) returns and refunds policy, and how to report a faulty or damaged product. Draft template pending legal review.">
  <meta property="og:url" content="{{ config('app.url') }}/returns">
  <meta property="og:image" content="{{ config('app.url') }}/assets/hero-warm-skincare.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Returns &amp; Refunds (Draft) — kotiva™">
  <meta name="twitter:description" content="The kotiva™ (Vitakode LLC) returns and refunds policy, and how to report a faulty or damaged product. Draft template pending legal review.">
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
      <h1 class="t-display reveal reveal-delay-1" style="margin-bottom:0;">RETURNS &amp;<br>REFUNDS</h1>
      <div class="draft-banner reveal reveal-delay-2">
        <strong>Draft — pending legal review.</strong> This page is a template prepared for internal
        review and has not yet been approved as kotiva™'s final Returns &amp; Refunds policy. Do not
        treat this as legally binding until it is reviewed and confirmed.
      </div>
    </div>
  </header>

  <section class="legal-content">
    <div class="container">
      <div class="legal-content-inner reveal">
        <p class="legal-meta">Last updated: October 2026 (draft)</p>

        <p>Because skincare and haircare products are personal-use items, we do not accept returns or
        exchanges once an order has been delivered. This applies to change of mind, to a product that
        turns out not to suit your skin type or concern, and to any product that has been opened or used.
        It applies to products bought on our website and, together with the retailer's own terms, to
        products bought from pharmacies and retailers.</p>

        <h2>The Exception: Faulty or Damaged Products</h2>
        <p>We will always put things right if a product is faulty or damaged, for example if it arrives
        broken or leaking, with a damaged or tampered seal, or with a manufacturing defect. Please check
        your order as soon as it is delivered.</p>

        <h2>How to Report a Problem</h2>
        <p>Contact us within 7 days of delivery at <a href="mailto:care@vitakode.com">care@vitakode.com</a>
        or on our WhatsApp customer care number:
        <a href="https://wa.me/21010101010" target="_blank" rel="noopener">+21010101010</a>. Please include
        your order number, or your receipt if you bought from a pharmacy or retailer, a short description
        of the problem, and clear photos of the product, its packaging and the batch code printed on it.
        Please keep the product and its packaging until we have replied, because we may ask you to send it
        back so we can inspect it. If we do, we will cover the cost of returning it.</p>

        <h2>What We Will Do</h2>
        <p>Once we have reviewed your report, we will tell you the outcome. If the product is confirmed to
        be faulty or damaged, we will send you a replacement or refund you, at your choice, excluding the
        delivery fee you paid for that item. Refunds go back to the original payment method within 10 to
        14 business days of approval. For cash on delivery orders, we will refund you by bank transfer. If
        you bought from a pharmacy or retailer, contact us first and we will guide you, and the refund may
        be handled together with the retailer.</p>

        <h2>If Your Skin Reacts to a Product</h2>
        <p>A reaction on your skin is not the same as a faulty product, so it is not covered by a refund.
        It is still important to us. Please stop using the product, see a doctor or pharmacist if the
        reaction is more than mild, and tell us what happened so we can record and look into it.</p>

        <h2>Questions</h2>
        <p>If your situation is not covered by this page, <a href="{{ route('contact') }}">contact our
        customer care team</a> and we will do our best to help.</p>
      </div>
    </div>
  </section>

@endsection
