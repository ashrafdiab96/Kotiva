#!/usr/bin/env node
/* ============================================================
   KOTIVA — Static Product Page Generator (W4)
   Build-time pre-render of 25 product/<slug>.html pages from
   js/data-lite.js + js/data-full.js, so non-JS crawlers (incl.
   most AI retrieval agents) get real title/meta/JSON-LD/content
   instead of an empty shell that only fills in via runtime JS.
   Does NOT touch product.html?id=N — that stays as a working
   legacy/compat route. Run: node scripts/generate-product-pages.js
   ============================================================ */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.join(__dirname, '..');
const SITE_ORIGIN = process.env.KOTIVA_SITE_ORIGIN || 'https://kotiva.co';
const OUT_DIR = path.join(ROOT, 'product');

function loadJsData(relPath, exportNames) {
  const code = fs.readFileSync(path.join(ROOT, relPath), 'utf8');
  const sandbox = { window: {}, module: { exports: {} }, exports: {}, console };
  vm.createContext(sandbox);
  const script = new vm.Script(code + '\n;module.exports = {' + exportNames.join(',') + '};', {
    filename: relPath,
  });
  script.runInContext(sandbox);
  return sandbox.module.exports;
}

const { KOTIVA_PRODUCTS } = loadJsData('js/data-lite.js', ['KOTIVA_PRODUCTS']);
const { KOTIVA_PRODUCTS_FULL } = loadJsData('js/data-full.js', ['KOTIVA_PRODUCTS_FULL']);

const fullById = new Map(KOTIVA_PRODUCTS_FULL.map(p => [p.id, p]));
const products = KOTIVA_PRODUCTS.map(p => Object.assign({}, p, fullById.get(p.id) || {}));

function esc(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function shortDesc(desc) {
  return desc && desc.length > 160 ? desc.slice(0, 157) + '…' : (desc || '');
}

/* ── E7: SCIENCE CONTENT STRUCTURE (2026-08-11) ───────────────────────────
   The reviewer's finding: product science is "long, highly technical text,
   making key benefits difficult to scan", and the recommendation was to break
   it into "short sections covering the concern, ingredient action, clinical
   result or supporting evidence".

   The client's own text ALREADY has that structure — named actives, "MOA:"
   lines, benefit bullets, then "Ref:" citations. The previous renderer
   flattened all of it to <br>-separated prose, discarding the hierarchy the
   client had written. So this is a PRESENTATION change over existing content:
   no science text is added, removed, reworded or reordered.

   Because that guarantee is the whole basis on which this ships without a
   claims review, it is ASSERTED rather than asserted-in-prose: assertNoLoss()
   throws at generate time if any client text or any citation URL fails to
   survive into the rendered output. A lossy page cannot be produced. */

function esc_(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function parseScience(raw) {
  const lines = String(raw || '').split('\n');
  const blocks = [];
  let cur = null;
  // Items are kept in an ORDERED list, not grouped by kind. Grouping would
  // silently reorder the client's own sequence — a content change wearing a
  // formatting change's clothes.
  const open = (name) => { cur = { name: name || '', items: [] }; blocks.push(cur); return cur; };
  const need = () => cur || open('');
  const push = (type, value) => {
    const b = need(), last = b.items[b.items.length - 1];
    if ((type === 'bullets' || type === 'refs') && last && last.type === type) last.value.push(value);
    else b.items.push({ type, value: (type === 'bullets' || type === 'refs') ? [value] : value });
  };
  const nextContent = (i) => {
    for (let j = i + 1; j < lines.length; j++) if (lines[j].trim()) return lines[j].trim();
    return '';
  };

  lines.forEach((line, i) => {
    const l = line.trim();
    if (!l) return;
    if (i === 0 && /ingredients\s*:?\s*$/i.test(l)) return;        // section header
    if (/^Ref\s*:?\s*$/i.test(l)) return;                          // "Ref:" label
    if (/^https?:\/\//i.test(l)) { push('refs', l); return; }
    if (/^MOA\s*:/i.test(l)) { push('moa', l.replace(/^MOA\s*:\s*/i, '')); return; }
    if (/^[•●*]\s*/.test(l) || /^\t/.test(line)) {
      push('bullets', l.replace(/^[•●*\t]\s*/, '').trim()); return;
    }
    const numbered = /^\d+\s*[-.)]\s*\S/.test(l);
    const short = l.length <= 70 && !/[.!?]$/.test(l);
    // An INGREDIENT heading is numbered, or is immediately followed by its
    // mechanism-of-action line. A line that merely ends in a colon ("Reduces:")
    // is a SUB-heading inside the current ingredient, not a new ingredient —
    // promoting those was making "Reduces" look like an active.
    if (short && (numbered || /^MOA\s*:/i.test(nextContent(i)) || !cur)) {
      open(l.replace(/:$/, '').replace(/^\d+\s*[-.)]\s*/, '').trim());
      return;
    }
    if (short && /:$/.test(l)) { push('sub', l.replace(/:$/, '').trim()); return; }
    push('para', l);
  });
  return blocks.filter(b => b.name || b.items.length);
}

function renderScience(blocks) {
  return blocks.map(b => {
    const parts = [];
    if (b.name) parts.push(`<h4 class="sci-name">${esc_(b.name)}</h4>`);
    b.items.forEach(it => {
      if (it.type === 'moa') {
        parts.push(`<p class="sci-line"><span class="sci-tag">How it works</span>${esc_(it.value)}</p>`);
      } else if (it.type === 'sub') {
        parts.push(`<p class="sci-sub">${esc_(it.value)}</p>`);
      } else if (it.type === 'bullets') {
        parts.push(`<ul class="sci-list">${it.value.map(x => `<li>${esc_(x)}</li>`).join('')}</ul>`);
      } else if (it.type === 'refs') {
        parts.push(`<p class="sci-refs"><span class="sci-tag">Evidence</span>${it.value.map(u =>
          `<a href="${esc_(u)}" target="_blank" rel="noopener noreferrer">source &#8599;</a>`).join(' ')}</p>`);
      } else {
        parts.push(`<p class="sci-line">${esc_(it.value)}</p>`);
      }
    });
    return `<div class="sci-block">${parts.join('')}</div>`;
  }).join('');
}

/* Throws if the presentation change silently dropped client content. */
function assertNoLoss(raw, html, productName) {
  const norm = (s) => String(s).replace(/\s+/g, ' ').trim();
  // Case-insensitive: the client's Anti-Hair Loss Shampoo text contains one
  // citation written "Https://…". A case-sensitive match here would silently
  // skip verifying it — the token check below is what exposed that.
  const srcUrls = (String(raw || '').match(/https?:\/\/[^\s]+/gi) || []);
  const outUrls = (html.match(/href="([^"]+)"/g) || []).map(h => h.slice(6, -1));
  srcUrls.forEach(u => {
    if (!outUrls.includes(u.replace(/&/g, '&amp;'))) {
      throw new Error(`E7 science render dropped a citation for "${productName}": ${u}`);
    }
  });
  // Every substantive source word must survive into the rendered text.
  // Compared on alphanumerics only: punctuation moves when a heading loses its
  // trailing colon, which is formatting, not content. The three STRUCTURAL
  // LABELS this renderer deliberately replaces are excluded by name — "MOA:"
  // becomes the "How it works" tag, "Ref:" becomes the "Evidence" tag, and the
  // "Key/Main active ingredients:" header becomes the section itself. Those are
  // the only removals allowed, and naming them here is what keeps the rest of
  // the assertion honest.
  const LABELS = new Set(['moa', 'ref', 'key', 'main', 'active', 'ingredients']);
  const words = (s) => norm(s).toLowerCase()
    .replace(/[^\p{L}\p{N}\s%]/gu, ' ').split(/\s+/).filter(Boolean);
  const outSet = new Set(words(html.replace(/<[^>]+>/g, ' ')
    .replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')));
  const srcWords = words(String(raw || '').replace(/https?:\/\/[^\s]+/gi, ' '))
    .filter(w => w.length > 2 && !LABELS.has(w));
  const missing = [...new Set(srcWords.filter(w => !outSet.has(w)))];
  if (missing.length) {
    throw new Error(`E7 science render dropped ${missing.length} token(s) for "${productName}": ${missing.slice(0, 6).join(' | ')}`);
  }
}

function scienceHtml(science, productName) {
  const html = renderScience(parseScience(science));
  assertNoLoss(science, html, productName || 'unknown');
  return html;
}

/* ── G2: ICON-LED PRODUCT FACTS (2026-08-11) ──────────────────────────────
   Slide 10 of the review: "Kotiva should introduce a similar icon-led product
   facts section to improve clarity, accessibility and purchase confidence."

   Every fact is drawn from data KOTIVA already supplied. Two of the facts the
   La Roche-Posay comparison shows are DELIBERATELY ABSENT:
     • free-from declarations — `freeFrom` is empty on all 25 products, and
       populating it from the client's documents is a claims-class correction
       requiring the evidence-package treatment, not a silent fill.
     • shelf life / period-after-opening — does not exist in any KOTIVA source.
   A fact with no data is omitted entirely. Nothing is inferred, and no
   placeholder is ever rendered — a blank fact would read as a fact. */
const ROUTINE_MODEL = JSON.parse(
  fs.readFileSync(path.join(ROOT, 'data/routine-model.json'), 'utf8'));

const ZONE_LABEL = {
  face: 'Face', body: 'Body', hair: 'Hair & scalp',
  lips: 'Lips', intimate: 'Intimate area'
};

const FACT_ICONS = {
  format: '<path d="M8 2h8M9 2v4.2a6 6 0 0 0-.9 3.1V19a3 3 0 0 0 3 3h1.8a3 3 0 0 0 3-3V9.3a6 6 0 0 0-.9-3.1V2"/>',
  zone:   '<circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
  skin:   '<path d="M12 3c4 4 6 6.7 6 9.6A6 6 0 0 1 6 12.6C6 9.7 8 7 12 3z"/>',
  target: '<circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="3.4"/>',
  size:   '<path d="M4 7h16M4 17h16M7 7v10M17 7v10"/>'
};

function factIcon(key) {
  return `<svg class="pdp-fact-icon" width="22" height="22" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true">${FACT_ICONS[key]}</svg>`;
}

function factsHtml(product) {
  const m = ROUTINE_MODEL.products[String(product.id)] || {};
  const zones = (m.zone || []).map(z => ZONE_LABEL[z]).filter(Boolean);
  const facts = [
    { k: 'format', label: 'Format',    value: m.format },
    { k: 'zone',   label: 'Use on',    value: zones.join(' & ') },
    { k: 'skin',   label: 'Skin type', value: product.skinType },
    { k: 'target', label: 'Targets',   value: product.concern },
    { k: 'size',   label: 'Size',      value: product.volume }
  ].filter(f => f.value && String(f.value).trim());

  if (!facts.length) return '';
  return `
      <div class="pdp-facts" aria-label="Product at a glance">
        ${facts.map(f => `<div class="pdp-fact">${factIcon(f.k)}<div><div class="pdp-fact-label">${esc(f.label)}</div><div class="pdp-fact-value">${esc(f.value)}</div></div></div>`).join('')}
      </div>`;
}

function relatedCards(product) {
  const related = products.filter(p => p.category === product.category && p.id !== product.id).slice(0, 4);
  return related.map(p => {
    const nm = p.name.replace(/^Kotiva\s+/i, '');
    return `<a class="product-card" href="/product/${p.slug}.html" style="border:none;">
      <div class="product-card-img"><img src="/${p.image}" alt="${esc(nm)}" loading="lazy"></div>
      <div class="product-card-body">
        <div class="product-card-name">${esc(nm)}</div>
        <div class="product-card-desc">${esc(p.category)} &middot; ${esc(p.skinType)}</div>
        <div class="product-card-footer"><span class="badge-concern">${esc(p.concern)}</span><span class="product-card-cta">Discover</span></div>
      </div>
    </a>`;
  }).join('\n');
}

/* Shop-filter zone taxonomy: breadcrumb position 2 must be a name/URL pair that
   agrees — the zone (filterTags[0]) IS the shop page's filter taxonomy, so use its
   pill label as the name and its data-filter token in the URL. Note: the 'spf'
   zone tag maps to shop.html's data-filter="sunscreen" pill (labelled "SPF"). */
const ZONE_CRUMBS = {
  face: { name: 'Face', filter: 'face' },
  body: { name: 'Body', filter: 'body' },
  serums: { name: 'Serums', filter: 'serums' },
  hair: { name: 'Hair', filter: 'hair' },
  spf: { name: 'SPF', filter: 'sunscreen' },
};

function pageHtml(product, index) {
  const pageUrl = `${SITE_ORIGIN}/product/${product.slug}.html`;
  const absImg = `${SITE_ORIGIN}/${product.image}`;
  const title = `${product.name} — kotiva™`;
  const desc = shortDesc(product.description);
  const ingPills = (product.ingredients || []).map(i => `<span class="pdp-ingredient-pill">${esc(i)}</span>`).join('');
  const freePills = (product.freeFrom || []).map(f => `<span class="pdp-free-pill">${esc(f)} Free</span>`).join('');
  const benefitsHtml = (product.benefits || []).map(b => `<li>${esc(b)}</li>`).join('');
  const sciHtml = scienceHtml(product.science, product.name);
  const related = relatedCards(product);
  const num = String(index + 1).padStart(2, '0');
  const total = String(products.length).padStart(2, '0');

  const productLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description,
    image: absImg,
    url: pageUrl,
    sku: product.kot,
    brand: { '@type': 'Brand', name: 'KOTIVA' },
    category: product.category,
    offers: {
      '@type': 'Offer',
      url: pageUrl,
      availability: 'https://schema.org/PreOrder',
      itemCondition: 'https://schema.org/NewCondition',
      ...(product.price ? {
        price: product.price.toFixed(2),
        priceCurrency: product.currency,
        valueAddedTaxIncluded: true,
      } : {}),
    },
  };
  const breadcrumbLd = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Products', item: `${SITE_ORIGIN}/shop.html` },
      (function () {
        const zone = ZONE_CRUMBS[(product.filterTags || [])[0]] || { name: 'All Products', filter: 'all' };
        return { '@type': 'ListItem', position: 2, name: zone.name, item: `${SITE_ORIGIN}/shop.html?filter=${encodeURIComponent(zone.filter)}` };
      })(),
      { '@type': 'ListItem', position: 3, name: product.name, item: pageUrl },
    ],
  };

  return `<!DOCTYPE html>
<html lang="en" data-theme="ritual">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${esc(title)}</title>
<meta name="description" content="${esc(desc)}">
<link rel="canonical" href="${pageUrl}">
<meta property="og:type" content="product">
<meta property="og:site_name" content="KOTIVA">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(desc)}">
<meta property="og:url" content="${pageUrl}">
<meta property="og:image" content="${absImg}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(desc)}">
<meta name="twitter:image" content="${absImg}">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=20260720">
<link rel="icon" type="image/png" sizes="48x48" href="/assets/favicon-48.png?v=20260720">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=20260720">
<link rel="preload" href="/fonts/FuturaNowVar-Roman.woff2?v=sub01" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/Montserrat-VariableFont_wght_25.woff2?v=sub01" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/css/kotiva.css?v=v4m">
<script type="application/ld+json">${JSON.stringify(productLd)}</script>
<script type="application/ld+json">${JSON.stringify(breadcrumbLd)}</script>
<style>
.pdp-hero { padding-top: var(--nav-h); display: grid; grid-template-columns: 1fr 1fr; min-height: 88vh; }
.pdp-gallery { position: sticky; top: var(--nav-h); height: calc(100vh - var(--nav-h)); overflow: hidden; background: #0e0c0a; display: flex; align-items: center; justify-content: center; }
.pdp-gallery-img { width: 70%; max-width: 420px; aspect-ratio: 3/4; object-fit: cover; }
.pdp-gallery-badge { position: absolute; top: 32px; left: 32px; font-family: var(--font-display); font-size: 8px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; background: var(--bronze); color: var(--black); padding: 6px 14px; }
/* K12: 0.2 alpha measured 1.72:1 on the #0e0c0a gallery — invisible, not merely low. 0.48 = 4.52:1. */
.pdp-gallery-num { position: absolute; bottom: 32px; right: 32px; font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.2em; color: rgba(245,240,232,0.48); }
.pdp-content { padding: clamp(48px, 6vw, 80px) clamp(36px, 5vw, 72px); border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 0; }
.pdp-breadcrumb { font-family: var(--font-display); font-size: 9px; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--fg-dim); margin-bottom: 28px; display: flex; gap: 12px; align-items: center; }
.pdp-breadcrumb a { color: inherit; transition: color 0.2s; }
.pdp-breadcrumb a:hover { color: var(--bronze); }
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
.pdp-free-pill { font-family: var(--font-display); font-size: 8px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; padding: 5px 12px; border: 1px solid rgba(100,180,100,0.2); color: rgba(100,200,140,0.6); }
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
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>
<nav class="nav transparent" id="main-nav" role="navigation">
  <div class="nav-inner">
    <a class="nav-logo" href="/index.html" aria-label="KOTIVA Home">
      <img class="logo-dark" src="/assets/kotiva-logo.svg?v=lg1" alt="KOTIVA" height="28" />
      <img class="logo-light" src="/assets/kotiva-logo-white.svg?v=lg1" alt="KOTIVA" height="28" style="display:none;" />
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
  <div class="pdp-hero">
    <div class="pdp-gallery">
      <div class="grain-overlay"></div>
      ${product.badge ? `<div class="pdp-gallery-badge">${esc(product.badge)}</div>` : ''}
      <img src="/${product.image}" alt="${esc(product.name)}" class="pdp-gallery-img">
      <div class="pdp-gallery-num">${num} / ${total}</div>
    </div>

    <div class="pdp-content">
      <div class="pdp-breadcrumb">
        <a href="/shop.html">Products</a>
        <span class="pdp-breadcrumb-sep">&rarr;</span>
        <span>${esc(product.category)}</span>
      </div>

      <div class="pdp-skin-type">${esc(product.skinType)}</div>
      <h1 class="pdp-name">${esc(product.name)}</h1>
      <div class="pdp-category">${esc(product.category)}${product.volume ? ` &middot; ${esc(product.volume)}` : ''}</div>
${product.price ? `      <div class="pdp-price"><span>${esc(product.currency)} ${product.price.toFixed(2)}</span><span class="pdp-price-vat">VAT included</span></div>\n` : ''}      <div class="pdp-claim">${esc(product.action)}</div>

      <div class="pdp-divider"></div>

      <p class="pdp-description">${esc(product.description)}</p>
${factsHtml(product)}
      ${benefitsHtml ? `
      <div class="pdp-benefits" style="margin-bottom:8px;">
        <p class="t-eyebrow" style="margin-bottom:12px; color:var(--fg-dim);">Key Benefits</p>
        <ul class="pdp-benefits-list">${benefitsHtml}</ul>
      </div>` : ''}

      ${ingPills ? `
      <div style="margin-bottom:8px;">
        <p class="t-eyebrow" style="margin-bottom:12px; color:var(--fg-dim);">Key Ingredients</p>
        <div class="pdp-ingredients-list">${ingPills}</div>
      </div>` : ''}

      ${freePills ? `<div class="pdp-free-from">${freePills}</div>` : ''}

      <div class="pdp-how-to">
        <div class="pdp-how-label">How to Use</div>
        <p class="pdp-how-text">${esc(product.howToUse) || 'Apply as directed. Part of The Kotiva Standard routine.'}</p>
      </div>

      ${sciHtml ? `
      <details class="pdp-science">
        <summary><span>The Science</span><span class="pdp-science-hint">Actives &amp; clinical references</span></summary>
        <div class="pdp-science-body">${sciHtml}</div>
      </details>` : ''}

      <div class="pdp-actions">
        <a href="/contact.html" class="btn btn-primary">Where to Buy</a>
        <a href="/routine-finder.html" class="btn btn-outline">Build Your Routine</a>
      </div>

      <div class="pdp-doctor-badge">
        <div class="pdp-doctor-icon">&#9877;</div>
        <div class="pdp-doctor-text">
          Doctor Approved &middot; Kotiva Standard<br>
          Formulated with clinically proven active ingredients
        </div>
      </div>
    </div>
  </div>

  ${related ? `
  <section class="related-section">
    <div class="container">
      <div class="section-header-row">
        <div>
          <p class="t-eyebrow" style="margin-bottom:12px;">You May Also Need</p>
          <h2 class="t-title">COMPLETE YOUR<br>ROUTINE</h2>
        </div>
        <a href="/shop.html" class="btn-text-light">View All &rarr;</a>
      </div>
      <div class="related-grid">${related}</div>
    </div>
  </section>` : ''}
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
}

fs.mkdirSync(OUT_DIR, { recursive: true });
let written = 0;
for (let i = 0; i < products.length; i++) {
  const product = products[i];
  if (!product.slug) {
    console.error(`SKIP: product id ${product.id} has no slug`);
    continue;
  }
  const outPath = path.join(OUT_DIR, `${product.slug}.html`);
  fs.writeFileSync(outPath, pageHtml(product, i), 'utf8');
  written++;
}
console.log(`Generated ${written}/${products.length} static product pages in ${path.relative(ROOT, OUT_DIR)}/`);
