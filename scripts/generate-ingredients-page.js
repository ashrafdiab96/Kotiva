#!/usr/bin/env node
/* ============================================================
   KOTIVA — Ingredient Glossary Page Generator (W5)
   Builds a single static /ingredients.html from the curated
   canonical actives (js/ingredients-glossary.js), cross-linked to
   the product pages that contain each active (from js/data-lite.js).
   Emits DefinedTermSet JSON-LD so AI answer engines can cite the
   glossary for "what does <active> do for skin" queries.
   Run: node scripts/generate-ingredients-page.js
   ============================================================ */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.join(__dirname, '..');
const SITE_ORIGIN = process.env.KOTIVA_SITE_ORIGIN || 'https://kotiva.co';

function loadJsData(relPath, exportNames) {
  const code = fs.readFileSync(path.join(ROOT, relPath), 'utf8');
  const sandbox = { window: {}, module: { exports: {} }, exports: {}, console };
  vm.createContext(sandbox);
  new vm.Script(code + '\n;module.exports = {' + exportNames.join(',') + '};').runInContext(sandbox);
  return sandbox.module.exports;
}

const { KOTIVA_PRODUCTS } = loadJsData('js/data-lite.js', ['KOTIVA_PRODUCTS']);
const { KOTIVA_INGREDIENTS } = loadJsData('js/ingredients-glossary.js', ['KOTIVA_INGREDIENTS']);

function esc(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// For each canonical active, find the products that list any of its aliases.
function productsFor(active) {
  const aliasSet = new Set(active.aliases);
  return KOTIVA_PRODUCTS.filter(p => (p.ingredients || []).some(i => aliasSet.has(i)));
}

const entries = KOTIVA_INGREDIENTS.map(a => ({ active: a, products: productsFor(a) }))
  .filter(e => e.products.length > 0)
  .sort((a, b) => a.active.name.localeCompare(b.active.name));

// DefinedTermSet JSON-LD
const definedTermSet = {
  '@context': 'https://schema.org',
  '@type': 'DefinedTermSet',
  name: 'KOTIVA Ingredient Glossary',
  description: 'Canonical reference of the key active ingredients used across KOTIVA doctor-approved skincare formulations.',
  url: `${SITE_ORIGIN}/ingredients.html`,
  hasDefinedTerm: entries.map(e => ({
    '@type': 'DefinedTerm',
    name: e.active.name,
    description: e.active.summary,
    url: `${SITE_ORIGIN}/ingredients.html#${e.active.slug}`,
    inDefinedTermSet: `${SITE_ORIGIN}/ingredients.html`,
  })),
};
const breadcrumbLd = {
  '@context': 'https://schema.org',
  '@type': 'BreadcrumbList',
  itemListElement: [
    { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE_ORIGIN}/` },
    { '@type': 'ListItem', position: 2, name: 'Ingredient Glossary', item: `${SITE_ORIGIN}/ingredients.html` },
  ],
};

function entryHtml(e) {
  const a = e.active;
  const also = a.also ? `<span class="ig-also">${esc(a.also)}</span>` : '';
  const productLinks = e.products.map(p => {
    const nm = p.name.replace(/^Kotiva\s+/i, '');
    return `<a class="ig-prod" href="/product/${p.slug}.html">${esc(nm)}</a>`;
  }).join('');
  return `
  <article class="ig-card" id="${a.slug}">
    <div class="ig-card-head">
      <div class="ig-cat">${esc(a.category)}</div>
      <h2 class="ig-name">${esc(a.name)}${also}</h2>
    </div>
    <p class="ig-summary">${esc(a.summary)}</p>
    <div class="ig-found">
      <span class="ig-found-label">Found in</span>
      <div class="ig-found-list">${productLinks}</div>
    </div>
  </article>`;
}

const cardsHtml = entries.map(entryHtml).join('\n');
const title = 'Ingredient Glossary — kotiva™';
const desc = 'A plain-language reference to the key active ingredients in KOTIVA skincare — what each active is and what it does for your skin.';

const html = `<!DOCTYPE html>
<html lang="en" data-theme="ritual">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<link rel="canonical" href="${SITE_ORIGIN}/ingredients.html">
<meta property="og:type" content="website">
<meta property="og:site_name" content="KOTIVA">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(desc)}">
<meta property="og:url" content="${SITE_ORIGIN}/ingredients.html">
<meta property="og:image" content="${SITE_ORIGIN}/assets/hero-warm-skincare.webp">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(desc)}">
<meta name="twitter:image" content="${SITE_ORIGIN}/assets/hero-warm-skincare.webp">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=20260720">
<link rel="icon" type="image/png" sizes="48x48" href="/assets/favicon-48.png?v=20260720">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=20260915">
<link rel="preload" href="/fonts/FuturaNowVar-Roman.woff2?v=sub01" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/Montserrat-VariableFont_wght_25.woff2?v=sub01" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/kotiva.css?v=kc1">
<script type="application/ld+json">${JSON.stringify(definedTermSet)}</script>
<script type="application/ld+json">${JSON.stringify(breadcrumbLd)}</script>
<style>
.ig-hero { padding-top: calc(var(--nav-h) + 80px); padding-bottom: 64px; border-bottom: 1px solid var(--border); }
.ig-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; background: var(--border); }
.ig-card { background: var(--bg); padding: clamp(28px, 3vw, 44px); display: flex; flex-direction: column; gap: 16px; scroll-margin-top: calc(var(--nav-h) + 24px); }
.ig-card-head { display: flex; flex-direction: column; gap: 10px; }
.ig-cat { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--accent-deep); }
.ig-name { font-family: var(--font-display); font-size: clamp(18px, 1.8vw, 24px); font-weight: 800; letter-spacing: 0.03em; text-transform: uppercase; color: var(--fg); line-height: 1.15; }
.ig-also { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.12em; color: var(--fg-dim); margin-top: 4px; text-transform: none; }
.ig-summary { font-size: 14px; line-height: 1.75; color: var(--fg-mid); font-family: var(--font-body); }
.ig-found { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); }
.ig-found-label { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--fg-dim); display: block; margin-bottom: 10px; }
.ig-found-list { display: flex; flex-wrap: wrap; gap: 8px; }
.ig-prod { font-family: var(--font-display); font-size: 10px; font-weight: 600; letter-spacing: 0.06em; padding: 5px 12px; border: 1px solid var(--border); color: var(--accent-deep); transition: color 0.2s, border-color 0.2s; }
.ig-prod:hover { color: var(--accent-text); border-color: var(--bronze); }
.ig-disclaimer { padding: clamp(32px, 4vw, 56px) 0; border-top: 1px solid var(--border); }
.ig-disclaimer p { font-size: 12px; line-height: 1.8; color: var(--fg-dim); font-family: var(--font-body); max-width: 720px; }
@media (max-width: 780px) { .ig-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>
<nav class="nav transparent" id="main-nav" role="navigation">
  <div class="nav-inner">
    <a class="nav-logo" href="/index.html" aria-label="KOTIVA Home">
      <img class="logo-dark" src="/assets/kotiva-logo.svg?v=lg1" alt="KOTIVA" height="28" />
      <img class="logo-light" src="/assets/kotiva-logo-white.svg?v=lg2" alt="KOTIVA" height="28" style="display:none;" />
    </a>
    <div class="nav-links" role="list">
      <a class="nav-link" href="/index.html" role="listitem">Home</a>
      <a class="nav-link" href="/shop.html" role="listitem">Shop</a>
      <a class="nav-link" href="/science.html" role="listitem">Science</a>
      <a class="nav-link" href="/journal.html" role="listitem">Journal</a>
      <a class="nav-link" href="/about.html" role="listitem">About</a>
      <a class="nav-link" href="/contact.html" role="listitem">Where to Buy</a>
    </div>
    <div class="nav-right">
      <a class="btn btn-gold btn-sm" href="/routine-finder.html">Find My Routine</a>
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

<div class="nav-overlay" role="dialog" aria-modal="true" aria-label="Navigation menu">
  <a class="nav-link" href="/index.html">Home</a>
  <a class="nav-link" href="/shop.html">Shop</a>
  <a class="nav-link" href="/science.html">Science</a>
  <a class="nav-link" href="/journal.html">Journal</a>
  <a class="nav-link" href="/about.html">About</a>
  <a class="nav-link" href="/contact.html">Where to Buy</a>
  <a class="btn btn-gold" href="/routine-finder.html">Find My Routine</a>
</div>

<main id="main-content">
  <header class="ig-hero">
    <div class="container">
      <p class="t-eyebrow" style="margin-bottom:16px;">Know Your Actives</p>
      <h1 class="t-display" style="margin-bottom:20px;">INGREDIENT<br>GLOSSARY</h1>
      <p class="t-body" style="max-width:560px;">A plain-language reference to the key active ingredients across the KOTIVA range — what each one is, and what it does for your skin. Every entry links to the products that use it, where the full clinical mechanism and references live.</p>
    </div>
  </header>

  <section class="section-pad-sm">
    <div class="container">
      <div class="ig-grid">
${cardsHtml}
      </div>
    </div>
  </section>

  <section class="ig-disclaimer">
    <div class="container">
      <p>This glossary describes the general function of each active ingredient. It is educational and does not constitute medical advice or a claim to treat, cure, or prevent any condition. Individual formulations, concentrations, and full clinical references are detailed on each product page. Patch-test new products and consult a healthcare professional for concerns specific to your skin.</p>
    </div>
  </section>
</main>

<footer class="footer" data-theme="ritual" role="contentinfo">
  <div class="container">
    <div class="footer-main">
      <div>
        <a class="footer-brand-link" href="/index.html" aria-label="KOTIVA — home"><img class="footer-brand-logo logo-dark" src="/assets/kotiva-logo.svg?v=lg1" alt="" /></a>
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
          <a href="/shop.html">All Products</a><a href="/shop.html?filter=face">Face Care</a>
          <a href="/shop.html?filter=body">Body Care</a><a href="/shop.html?filter=serums">Serums</a>
          <a href="/shop.html?filter=sunscreen">SPF</a><a href="/shop.html?filter=hair">Hair</a>
        </nav>
      </div>
      <div><div class="footer-col-title">Company</div>
        <nav class="footer-links" aria-label="Company links">
          <a href="/about.html">About Kotiva</a><a href="/science.html">Our Science</a><a href="/ingredients.html">Ingredient Glossary</a>
          <a href="/routine-finder.html">Routine Finder</a><a href="/journal.html">Journal</a><a href="/index.html#newsletter">Newsletter</a>
          <a href="/contact.html">Contact Us</a><a href="/assets/kotiva-company-profile.pdf" target="_blank" rel="noopener">Company Profile (PDF)</a>
        </nav>
      </div>
      <div><div class="footer-col-title">Support</div>
        <nav class="footer-links" aria-label="Support links">
          <a href="/privacy-policy.html">Privacy Policy</a><a href="/terms.html">Terms of Use</a><a href="/contact.html#faq">FAQ</a>
        </nav>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; 2026 KOTIVA&trade;, a brand of Vitakode LLC. All rights reserved.</span>
      <span>Doctor-approved skincare tailored for you.</span>
    </div>
  </div>
</footer>

<script src="/js/layout.js?v=v3s"></script>
<script src="/js/animations.js?v=v3m"></script>
</body>
</html>
`;

fs.writeFileSync(path.join(ROOT, 'ingredients.html'), html, 'utf8');
console.log(`Generated ingredients.html with ${entries.length} canonical actives (${KOTIVA_INGREDIENTS.length - entries.length} defined but unused).`);
