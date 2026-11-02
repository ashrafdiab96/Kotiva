@extends('layouts.app')

@section('meta')
  <title>Privacy Policy (Draft) — kotiva™</title>
  <meta name="description" content="How kotiva™ (Vitakode LLC) collects, uses, and protects your personal data. Draft template pending legal review.">
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="{{ config('app.url') }}/privacy-policy">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Privacy Policy (Draft) — kotiva™">
  <meta property="og:description" content="How kotiva™ (Vitakode LLC) collects, uses, and protects your personal data. Draft template pending legal review.">
  <meta property="og:url" content="{{ config('app.url') }}/privacy-policy">
  <meta property="og:image" content="{{ config('app.url') }}/assets/hero-warm-skincare.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Privacy Policy (Draft) — kotiva™">
  <meta name="twitter:description" content="How kotiva™ (Vitakode LLC) collects, uses, and protects your personal data. Draft template pending legal review.">
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
      <h1 class="t-display reveal reveal-delay-1" style="margin-bottom:0;">PRIVACY<br>POLICY</h1>
      <div class="draft-banner reveal reveal-delay-2">
        <strong>Draft — pending legal review.</strong> This page is a template prepared for internal
        review and has not yet been approved as kotiva™'s final Privacy Policy. Do not treat this as
        legally binding until it is reviewed and confirmed.
      </div>
    </div>
  </header>

  <section class="legal-content">
    <div class="container">
      <div class="legal-content-inner reveal">
        <p class="legal-meta">Last updated: October 2026 (draft)</p>

        <p>This Privacy Policy explains how kotiva™, a brand of Vitakode LLC ("kotiva", "we", "us"),
        collects, uses and protects your personal data when you visit our website, take our Routine
        Finder quiz, subscribe to our newsletter, contact us, or buy our products. We want you to know
        what we do with your information, and we collect only what we need to look after you and run
        the brand.</p>

        <h2>What We Collect</h2>
        <p>When you contact us or fill in a form, we collect the details you give us, such as your name,
        email address, phone number and message. When you subscribe to the Journal newsletter, we collect
        your email address. When you use the Routine Finder, we collect the answers you choose about your
        skin type, main concern and experience level. If you order from us online, we also collect your
        delivery address, order details and payment confirmation. Payments are handled by our payment
        provider, and we do not store your full card details. Like most websites, we also collect basic
        technical information, such as your device, browser, pages visited and approximate location,
        through cookies and similar tools.</p>

        <h2>Why We Use It</h2>
        <p>We use your information to answer your questions, process and deliver orders, handle faulty or
        damaged product reports, send the newsletter you asked for, and give you routine suggestions that
        match your answers. We also use it to understand how the website is used so we can improve it,
        and to meet our legal obligations. We use your Routine Finder answers only to build your suggested
        routine and to improve the quiz. The quiz offers product guidance and does not diagnose any skin
        condition. We only send you marketing messages if you have agreed to receive them, and you can
        unsubscribe at any time using the link in every email.</p>

        <h2>Who We Share It With</h2>
        <p>We share personal data only with people who help us run kotiva, such as delivery companies, our
        payment provider, website hosting and analytics services, and email service providers, and only
        for the purpose they were engaged for. If you buy from a pharmacy or retailer and contact us about
        the purchase, we may speak to that retailer to resolve it. We may also share information where the
        law requires it. We do not sell your personal data.</p>

        <h2>Where It Is Processed and How Long We Keep It</h2>
        <p>kotiva is founded in Oman, our products are manufactured in Poland, and our service providers
        may be located in other countries, so your data may be processed outside the country where you
        live. When this happens, we take steps to protect it in line with applicable law. We keep personal
        data only as long as we need it for the purposes above or as the law requires: 24 months after
        your last interaction for enquiries and quiz answers, and the period required by law for order
        records.</p>

        <h2>How We Protect It</h2>
        <p>We use reasonable technical and organisational measures to keep your data secure, including
        access controls and encrypted connections. No online service can promise perfect security, so
        please use a strong password if you create an account and contact us straight away if you suspect
        your account has been misused.</p>

        <h2>Your Rights</h2>
        <p>Under the Saudi Personal Data Protection Law and other data protection laws that apply to you,
        you have the right to know how your data is used, to ask for a copy of it, to ask us to correct it
        or delete it, and to withdraw your consent at any time. To use any of these rights, contact us
        using the details below. We will respond within the time the law requires and may need to confirm
        your identity first. If you are not satisfied with our response, you can complain to your local
        data protection authority.</p>

        <h2>Cookies</h2>
        <p>We use cookies that are necessary to make the website work, and, with your consent where the law
        requires it, cookies that measure how the site is used. You can change your cookie choices at any
        time in your browser settings.</p>

        <h2>Changes and Contact</h2>
        <p>We may update this policy from time to time and will show the date of the latest version at the
        top of this page. If you have any question about it, contact our customer care team at
        <a href="mailto:care@vitakode.com">care@vitakode.com</a>.</p>
      </div>
    </div>
  </section>

@endsection
