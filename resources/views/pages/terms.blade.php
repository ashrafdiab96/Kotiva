@extends('layouts.app')

@section('meta')
  <title>Terms & Conditions (Draft) — kotiva™</title>
  <meta name="description" content="The terms and conditions governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="{{ config('kotiva.site_origin') }}/terms">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Terms & Conditions (Draft) — kotiva™">
  <meta property="og:description" content="The terms and conditions governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta property="og:url" content="{{ config('kotiva.site_origin') }}/terms">
  <meta property="og:image" content="{{ config('kotiva.site_origin') }}/assets/hero-warm-skincare.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Terms & Conditions (Draft) — kotiva™">
  <meta name="twitter:description" content="The terms and conditions governing your use of the kotiva™ (Vitakode LLC) website. Draft template pending legal review.">
  <meta name="twitter:image" content="{{ config('kotiva.site_origin') }}/assets/hero-warm-skincare.webp">
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
      <h1 class="t-display reveal reveal-delay-1" style="margin-bottom:0;">TERMS &<br>CONDITIONS</h1>
      <div class="draft-banner reveal reveal-delay-2">
        <strong>Draft — pending legal review.</strong> This page is a template prepared for internal
        review and has not yet been approved as kotiva™'s final Terms & Conditions. Do not treat this
        as legally binding until it is reviewed and confirmed.
      </div>
    </div>
  </header>

  <section class="legal-content">
    <div class="container">
      <div class="legal-content-inner reveal">
        <p class="legal-meta">Last updated: 5 July 2026 (draft)</p>

        <h2>1. Acceptance of Terms</h2>
        <p>These Terms & Conditions ("Terms") govern your use of this website
        (kotiva.co / kotiva.co, the "Site"), operated by <strong>Vitakode LLC</strong>,
        a company registered in Muscat, Sultanate of Oman ("kotiva™", "we", "us", or "our"). By
        accessing or using the Site, you agree to be bound by these Terms.</p>

        <h2>2. About Our Products</h2>
        <p>kotiva™ products are doctor-reviewed skincare formulations. Product descriptions,
        ingredient information, and usage instructions on this Site are provided for informational
        purposes and do not constitute medical advice. Always patch-test a new product and consult a
        dermatologist or physician if you have a skin condition, are pregnant or nursing, or are
        uncertain whether a product is suitable for you.</p>

        <h2>3. Online Ordering</h2>
        <p>Online ordering is not yet available on this Site. Where the Site indicates a "coming soon"
        or waitlist feature for online ordering, no purchase can currently be made through the Site.
        Once online ordering launches, these Terms will be updated to include order acceptance,
        payment, shipping, and cancellation terms.</p>

        <h2>4. Purchases Through Stockists</h2>
        <p>kotiva™ products may currently be purchased through select third-party stockists. Purchases
        made through a stockist are subject to that stockist's own terms of sale, return policy, and
        payment terms, not these Terms.</p>

        <h2>5. Intellectual Property</h2>
        <p>All content on this Site — including text, graphics, logos, product photography, and
        the kotiva™ name and marks — is the property of Vitakode LLC or its licensors and is
        protected by applicable intellectual property laws. You may not reproduce, distribute, or
        create derivative works from this content without our prior written consent.</p>

        <h2>6. Acceptable Use</h2>
        <p>You agree not to use the Site in any way that is unlawful, or to attempt to gain
        unauthorized access to any part of the Site or its underlying systems.</p>

        <h2>7. Limitation of Liability</h2>
        <p>To the fullest extent permitted by applicable law, Vitakode LLC shall not be liable for any
        indirect, incidental, or consequential damages arising from your use of the Site or reliance
        on any information published on it.</p>

        <h2>8. Governing Law</h2>
        <p>These Terms are governed by the laws of the Sultanate of Oman, without regard to its
        conflict-of-law principles. Any disputes arising under these Terms shall be subject to the
        exclusive jurisdiction of the courts of Muscat, Oman.</p>

        <h2>9. Changes to These Terms</h2>
        <p>We may revise these Terms from time to time. The "Last updated" date at the top of this
        page reflects the most recent revision. Continued use of the Site after changes are posted
        constitutes acceptance of the revised Terms.</p>

        <h2>10. Contact Us</h2>
        <p>Questions about these Terms can be directed to:</p>
        <p>
          Vitakode LLC<br>
          North Al Ghubra, Bousher, Muscat Governorate, Sultanate of Oman<br>
          Email: <a href="mailto:info@vitakode.com">info@vitakode.com</a><br>
          Phone: <a href="tel:+96895990420">+968 9599 0420</a>
        </p>
      </div>
    </div>
  </section>

@endsection
