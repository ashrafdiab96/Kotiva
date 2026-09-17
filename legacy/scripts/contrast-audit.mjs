#!/usr/bin/env node
/* ============================================================
   Programmatic WCAG contrast audit for the brand-colour migration (brief §6.8).

   For every page family x {desktop 1440, mobile 390} x {light, dark}:
   * every rendered text element is measured against its REAL backdrop — the paint stack under
     the text (document.elementsFromPoint), compositing every translucent background (rgba
     surfaces, flat single-colour gradients) over what is actually beneath it, and multiplying in
     the opacity of the groups that contain the text but not the backdrop;
   * text over photography or a real gradient is NOT judged from CSS: the text is hidden, the
     glyph box is screenshotted, and the contrast is computed against every sampled pixel. It
     passes only if the 5th-percentile ratio meets the threshold (>= 95% of the backdrop pixels);
   * interaction states and non-text UI are exercised explicitly (skip link, nav states, hovers,
     focus-visible indicators, filter pills, form success/error, placeholders, error outline).

   Thresholds: 4.5:1 normal text; 3:1 large text (>= 24px, or >= 19px at weight >= 700) and
   required non-text UI. Decorative = effective text alpha < 0.15 (ghost glyphs); informational
   rows (boundaries that are not the sole identifier of a control) are reported, not gated.
   A failing handwritten/script element in #D7282F is BLOCKED — DESIGNER SIGN-OFF, never PASS.

   Usage: node scripts/contrast-audit.mjs [--pages home,shop]
   Writes docs/colour-migration/contrast-audit.{json,md}; exit 1 on any FAIL.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { startServer } from './lib/static-server.mjs';
import { PAGE_FAMILIES, CELLS, newModeContext, settle } from './lib/pages.mjs';
import { normalised } from './lib/colour-literals.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, def) => {
  const i = process.argv.indexOf(`--${name}`);
  return i === -1 ? def : process.argv[i + 1];
};
const onlyPages = arg('pages') ? arg('pages').split(',') : null;
const families = PAGE_FAMILIES.filter((f) => !onlyPages || onlyPages.includes(f.id));
// --root audits another checkout (e.g. the pre-migration worktree); --label keeps its report separate.
const serveRoot = path.resolve(ROOT, arg('root', '.'));
const reportName = 'contrast-audit' + (arg('label') ? '-' + arg('label') : '');

const HIDE_CSS = '[data-ca-hide], [data-ca-hide] * { color: transparent !important; -webkit-text-fill-color: transparent !important; text-shadow: none !important; }';

/* ---------- in-page helpers ---------- */
function installHelpers() {
  const lin = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const ratio = (a, b) => { const x = lum(a); const y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const comp = (t, b) => ({ r: t.r * t.a + b.r * (1 - t.a), g: t.g * t.a + b.g * (1 - t.a), b: t.b * t.a + b.b * (1 - t.a), a: 1 });
  const parse = (s) => {
    if (!s) return null;
    if (s === 'transparent') return { r: 0, g: 0, b: 0, a: 0 };
    const m = s.match(/rgba?\(([^)]*)\)/);
    if (!m) return null;
    const p = m[1].split(/[\s,/]+/).filter(Boolean).map(parseFloat);
    return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
  };
  const flat = (bi) => {
    if (!bi || bi === 'none') return { none: true };
    if (bi.includes('url(')) return null;
    const cols = bi.match(/rgba?\([^)]*\)/g);
    if (!cols) return null;
    return new Set(cols.map((c) => c.replace(/\s+/g, ''))).size === 1 ? parse(cols[0]) : null;
  };
  const MEDIA = new Set(['IMG', 'VIDEO', 'CANVAS', 'PICTURE', 'IFRAME', 'OBJECT', 'EMBED']);
  const selectorPath = (el) => {
    const parts = [];
    for (let e = el, k = 0; e && e !== document.body && k < 3; e = e.parentElement, k++) {
      parts.unshift(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + [...e.classList].slice(0, 2).map((c) => '.' + c).join(''));
    }
    return parts.join(' > ');
  };
  const textRects = (el) => {
    const out = [];
    for (const n of el.childNodes) {
      if (n.nodeType !== 3 || !n.textContent.trim()) continue;
      const r = document.createRange();
      r.selectNodeContents(n);
      out.push(...[...r.getClientRects()].filter((x) => x.width > 1 && x.height > 1));
    }
    return out;
  };
  const firstRect = (el, box) => {
    const rs = textRects(el);
    if (rs.length) return rs[0];
    if (box) { const b = el.getBoundingClientRect(); if (b.width > 1 && b.height > 1) return b; }
    return null;
  };

  /* skipSelfImage: ignore the element's OWN background-image (e.g. a <select> chevron drawn as a small
     data-URI icon) — it sits beside the text, not under it — while still honouring its background colour. */
  function backdrop(el, rect, includeSelf, skipSelfImage) {
    const cx = Math.min(innerWidth - 1, Math.max(0, rect.left + rect.width / 2));
    const cy = Math.min(innerHeight - 1, Math.max(0, rect.top + rect.height / 2));
    const stack = document.elementsFromPoint(cx, cy);
    let idx = stack.indexOf(el);
    if (idx === -1) idx = stack.findIndex((s) => s.contains(el));
    if (idx === -1) return { occluded: true };
    const covered = stack.slice(0, idx).some((s) => {
      if (el.contains(s) || s.contains(el)) return false;
      const cs = getComputedStyle(s);
      const bg = parse(cs.backgroundColor);
      return MEDIA.has(s.tagName) || (bg && bg.a > 0.5) || cs.backgroundImage !== 'none';
    });
    const layers = [];
    let complex = null;
    let bottom = null;
    for (let j = includeSelf ? idx : idx + 1; j < stack.length; j++) {
      const e = stack[j];
      if (e !== el && !e.contains(el) && MEDIA.has(e.tagName)) { complex = 'image'; bottom = e; break; }
      const cs = getComputedStyle(e);
      const f = skipSelfImage && e === el ? { none: true } : flat(cs.backgroundImage);
      if (f === null) { complex = 'gradient/image'; bottom = e; break; }
      if (!f.none && f.a > 0) layers.push(f);
      if (!f.none && f.a >= 1) { bottom = e; break; }
      const bg = parse(cs.backgroundColor);
      if (bg && bg.a > 0) { layers.push(bg); if (bg.a >= 1) { bottom = e; break; } }
    }
    let colour = { r: 255, g: 255, b: 255, a: 1 };
    if (!complex) for (let k = layers.length - 1; k >= 0; k--) colour = comp(layers[k], colour);
    return { covered, complex, bottom, colour };
  }

  function analyse(el, opts = {}) {
    if (!el) return { missing: true };
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') return null;
    let rect = firstRect(el, opts.box);
    if (!rect) return null;
    if (!opts.noScroll) {
      window.scrollTo(window.scrollX, Math.max(0, rect.top + window.scrollY - innerHeight / 2 + rect.height / 2));
      rect = firstRect(el, opts.box);
    }
    const sel = selectorPath(el);
    if (!rect || rect.bottom <= 0 || rect.top >= innerHeight || rect.right <= 0 || rect.left >= innerWidth) return { sel, offscreen: true };
    const bd = backdrop(el, rect, true, !!opts.skipSelfImage);
    if (bd.occluded || bd.covered) return { sel, occluded: true };
    let op = 1;
    for (let a = el; a && a !== document.documentElement; a = a.parentElement) {
      if (bd.bottom && a.contains(bd.bottom)) break;
      op *= parseFloat(getComputedStyle(a).opacity);
    }
    const src = opts.pseudo ? getComputedStyle(el, opts.pseudo) : cs;
    const fg = parse(src.webkitTextFillColor || src.color) || parse(src.color);
    if (!fg) return null;
    const eff = { ...fg, a: fg.a * op };
    const size = parseFloat(src.fontSize);
    const weight = parseInt(src.fontWeight, 10) || 400;
    const large = size >= 24 || (size >= 19 && weight >= 700);
    const themeEl = el.closest('[data-theme]:not(html)');
    const out = {
      sel,
      text: (opts.label || el.textContent || el.value || '').replace(/\s+/g, ' ').trim().slice(0, 48),
      size, weight, large, threshold: large ? 3 : 4.5,
      theme: themeEl ? themeEl.getAttribute('data-theme') : 'root',
      script: /manus/i.test(src.fontFamily),
      fg: eff,
      hidden: eff.a === 0,
      // Pure decoration (WCAG 1.4.3 exception): ghost glyphs, and text made only of symbols/punctuation
      // (✦ separators, → arrows) that carries no words or numbers.
      decorative: (eff.a > 0 && eff.a < 0.15) || !/[\p{L}\p{N}]/u.test(el.textContent || ''),
      // Inactive UI components are exempt from the contrast requirement.
      disabled: !!el.closest(':disabled, [aria-disabled="true"]'),
    };
    if (bd.complex) out.complex = bd.complex;
    else { out.bg = bd.colour; out.ratio = ratio(comp(eff, bd.colour), bd.colour); }
    return out;
  }

  function nonText(selector, prop) {
    const el = document.querySelector(selector);
    if (!el) return { missing: true };
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const c = parse(cs[prop]);
    if (!c) return { missing: true, raw: cs[prop] };
    const bd = backdrop(el, r, false);
    const styleProp = prop.startsWith('outline') ? 'outlineStyle' : prop.startsWith('border') ? prop.replace('Color', 'Style') : null;
    const res = { sel: selectorPath(el), value: c, style: styleProp ? cs[styleProp] : '' };
    if (bd.complex) return { ...res, complex: bd.complex };
    return { ...res, bg: bd.colour, ratio: ratio(comp(c, bd.colour), bd.colour) };
  }

  function placeholder(selector) {
    const el = document.querySelector(selector);
    if (!el) return { missing: true };
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    const ph = getComputedStyle(el, '::placeholder');
    const fg = parse(ph.color);
    const bd = backdrop(el, r, true);
    const eff = { ...fg, a: fg.a * parseFloat(ph.opacity || '1') };
    const size = parseFloat(ph.fontSize);
    const large = size >= 24 || (size >= 19 && (parseInt(ph.fontWeight, 10) || 400) >= 700);
    const res = { sel: selectorPath(el) + '::placeholder', text: el.getAttribute('placeholder') || '', size, large, threshold: large ? 3 : 4.5, fg: eff };
    if (bd.complex) return { ...res, complex: bd.complex };
    return { ...res, bg: bd.colour, ratio: ratio(comp(eff, bd.colour), bd.colour) };
  }

  function chevron(selector) {
    const el = document.querySelector(selector);
    if (!el) return { missing: true };
    const m = getComputedStyle(el).backgroundImage.match(/%23([0-9a-fA-F]{6})/);
    if (!m) return { missing: true };
    const h = m[1];
    const c = { r: parseInt(h.slice(0, 2), 16), g: parseInt(h.slice(2, 4), 16), b: parseInt(h.slice(4, 6), 16), a: 1 };
    el.scrollIntoView({ block: 'center' });
    const bd = backdrop(el, el.getBoundingClientRect(), true, true);
    return { sel: selectorPath(el) + ' (chevron)', value: c, bg: bd.colour, ratio: bd.complex ? null : ratio(c, bd.colour) };
  }

  function collect() {
    const els = [];
    const seen = new Set();
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (w.nextNode()) {
      const t = w.currentNode;
      if (!t.textContent.trim()) continue;
      const el = t.parentElement;
      if (!el || seen.has(el)) continue;
      seen.add(el);
      if (el.closest('script,style,noscript,template,select,option,svg,title')) continue;
      els.push(el);
    }
    window.__caEls = els;
    return els.length;
  }
  function analyseAll() {
    const res = [];
    window.__caEls.forEach((el, i) => { const r = analyse(el); if (r) res.push({ i, ...r }); });
    window.scrollTo(0, 0);
    return res;
  }
  window.__ca = { analyse, nonText, placeholder, chevron, collect, analyseAll, selectorPath, textRects };
}

/* ---------- pixel sampling (runs in a blank decoder page) ---------- */
async function samplePixels({ b64, fg, threshold }) {
  const img = new Image();
  img.src = 'data:image/png;base64,' + b64;
  await img.decode();
  const c = document.createElement('canvas');
  c.width = img.width;
  c.height = img.height;
  const ctx = c.getContext('2d', { willReadFrequently: true });
  ctx.drawImage(img, 0, 0);
  const d = ctx.getImageData(0, 0, c.width, c.height).data;
  const lin = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  const L = (r, g, b) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
  const total = c.width * c.height;
  const step = Math.max(1, Math.floor(total / 40000));
  const ratios = [];
  for (let p = 0; p < total; p += step) {
    const i = p * 4;
    const br = d[i]; const bg = d[i + 1]; const bb = d[i + 2];
    const tr = fg.r * fg.a + br * (1 - fg.a);
    const tg = fg.g * fg.a + bg * (1 - fg.a);
    const tb = fg.b * fg.a + bb * (1 - fg.a);
    const l1 = L(tr, tg, tb); const l2 = L(br, bg, bb);
    ratios.push((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05));
  }
  ratios.sort((a, b) => a - b);
  const q = (f) => ratios[Math.min(ratios.length - 1, Math.floor(f * ratios.length))];
  return { n: ratios.length, min: ratios[0], p5: q(0.05), p50: q(0.5), under: ratios.filter((x) => x < threshold).length / ratios.length };
}

/* ---------- state scenarios ---------- */
const desktop = ['desktop-light', 'desktop-dark'];
const mobile = ['m390-light', 'm390-dark'];
const hover = (sel) => async (p) => { await p.hover(sel, { timeout: 5000 }); await p.waitForTimeout(450); };
const kbFocus = (sel) => async (p) => { await p.keyboard.press('Tab'); await p.focus(sel); await p.waitForTimeout(200); };
const navScrolled = async (p) => {
  await p.evaluate(() => window.scrollTo(0, 700));
  await p.waitForTimeout(200);
  await p.evaluate(() => window.scrollTo(0, 640));
  await p.waitForTimeout(500);
};
const SCENARIOS = [
  { page: 'home', name: 'Skip link (focused)', prep: async (p) => { await p.evaluate(() => window.scrollTo(0, 0)); await p.keyboard.press('Tab'); await p.waitForTimeout(350); }, text: '.skip-link', opts: { noScroll: true } },
  { page: 'about', name: 'Skip link (focused)', prep: async (p) => { await p.evaluate(() => window.scrollTo(0, 0)); await p.keyboard.press('Tab'); await p.waitForTimeout(350); }, text: '.skip-link', opts: { noScroll: true } },
  { page: 'home', name: 'Nav transparent — link over hero', cells: desktop, text: '.nav-links .nav-link:not(.is-current)', opts: { noScroll: true } },
  { page: 'home', name: 'Nav transparent — current link over hero', cells: desktop, text: '.nav-links .nav-link.is-current', opts: { noScroll: true } },
  { page: 'home', name: 'Nav scrolled — link', cells: desktop, prep: navScrolled, text: '.nav-links .nav-link:not(.is-current)', opts: { noScroll: true } },
  { page: 'home', name: 'Nav scrolled — link hover', cells: desktop, prep: async (p) => { await navScrolled(p); await hover('.nav-links .nav-link:not(.is-current)')(p); }, text: '.nav-links .nav-link:not(.is-current)', opts: { noScroll: true } },
  { page: 'about', name: 'Nav on inner page — link', cells: desktop, text: '.nav-links .nav-link:not(.is-current)', opts: { noScroll: true } },
  { page: 'home', name: 'Primary button (nav CTA) hover', cells: desktop, prep: hover('.nav-right .btn-gold'), text: '.nav-right .btn-gold', opts: { noScroll: true } },
  { page: 'home', name: 'Primary button (editorial section) hover', prep: hover('.doctor-section .btn-gold'), text: '.doctor-section .btn-gold' },
  { page: 'home', name: 'Primary button (ritual newsletter) hover', prep: hover('#home-nl-form .btn-gold'), text: '#home-nl-form .btn-gold' },
  { page: 'home', name: 'Secondary button over hero photo hover', prep: hover('.hero .btn-outline-light'), text: '.hero .btn-outline-light' },
  { page: 'home', name: 'Newsletter input focus border', prep: async (p) => { await p.focus('#home-nl-email'); await p.waitForTimeout(400); }, nonText: ['#home-nl-email', 'borderTopColor'], min: 3 },
  { page: 'home', name: 'Newsletter placeholder', placeholder: '#home-nl-email' },
  { page: 'home', name: 'Footer logo link focus-visible', prep: kbFocus('.footer-brand-link'), nonText: ['.footer-brand-link', 'outlineColor'], min: 3 },
  { page: 'home', name: 'Mobile menu open — link', cells: mobile, prep: async (p) => { await p.click('.nav-hamburger'); await p.waitForTimeout(600); }, text: '.nav-overlay .nav-link:not(.is-current)', opts: { noScroll: true } },
  { page: 'home', name: 'Mobile menu open — current link', cells: mobile, prep: async (p) => { await p.click('.nav-hamburger'); await p.waitForTimeout(600); }, text: '.nav-overlay .nav-link.is-current', opts: { noScroll: true } },
  { page: 'shop', name: 'Filter pill active — label', text: '.filter-pill.active' },
  { page: 'shop', name: 'Filter pill active — fill vs bar', nonText: ['.filter-pill.active', 'backgroundColor'], min: 3 },
  { page: 'shop', name: 'Filter pill hover (inactive)', prep: hover('.filter-pill:not(.active)'), text: '.filter-pill:not(.active)' },
  { page: 'shop', name: 'Filter pill inactive border', nonText: ['.filter-pill:not(.active)', 'borderTopColor'], informational: true },
  { page: 'contact', name: 'Form success message', prep: async (p) => { await p.evaluate(() => { document.getElementById('cf-success').style.display = 'block'; }); }, text: '#cf-success' },
  { page: 'contact', name: 'Form error message', prep: async (p) => { await p.evaluate(() => { document.getElementById('cf-error').style.display = 'block'; }); }, text: '#cf-error' },
  { page: 'contact', name: 'Form submit hover', prep: hover('.form-submit'), text: '.form-submit' },
  { page: 'contact', name: 'FAQ question focus-visible', prep: kbFocus('.faq-q'), nonText: ['.faq-q', 'outlineColor'], min: 3 },
  { page: 'contact', name: 'Select value text', text: 'select.form-field', opts: { box: true, skipSelfImage: true } },
  { page: 'contact', name: 'Select chevron', chevron: 'select.form-field', informational: true },
  { page: 'contact', name: 'Field placeholder', placeholder: 'input.form-field' },
  { page: 'contact', name: 'Field bottom border', nonText: ['input.form-field', 'borderBottomColor'], informational: true },
  { page: 'journal', name: 'Newsletter error outline', prep: async (p) => { await p.fill('#j-email', 'abc'); await p.evaluate(() => handleJournalNL()); await p.waitForTimeout(100); }, nonText: ['#j-email', 'outlineColor'], min: 3, signoff: 'status' },
  { page: 'journal', name: 'Newsletter subscribed button', prep: async (p) => { await p.evaluate(() => { const b = document.getElementById('j-email').nextElementSibling; b.textContent = 'Subscribed ✓'; b.style.background = '#004539'; b.style.color = '#fff'; }); await p.waitForTimeout(450); }, text: '#j-email + *' },
  { page: 'journal', name: 'Newsletter placeholder', placeholder: '#j-email' },
  { page: 'journal', name: 'Filter button active', text: '.jf-btn.active' },
  { page: 'routine-finder', name: 'Quiz option hover — title', prep: hover('.quiz-step.active .quiz-option'), text: '.quiz-step.active .quiz-option .quiz-option-title' },
  { page: 'routine-finder', name: 'Quiz option selected — title', prep: async (p) => { await p.click('.quiz-step.active .quiz-option'); await p.mouse.move(0, 0); await p.waitForTimeout(450); }, text: '.quiz-option.selected .quiz-option-title' },
  { page: 'product', name: 'Breadcrumb link hover', prep: hover('.pdp-breadcrumb a'), text: '.pdp-breadcrumb a' },
  { page: 'ingredients', name: 'Glossary product link hover', prep: hover('.ig-prod'), text: '.ig-prod' },
];

function classify(r, { informational = false, signoff = null } = {}) {
  if (r.missing) return 'MISSING';
  if (r.offscreen) return 'OFFSCREEN';
  if (r.occluded) return 'OCCLUDED';
  if (r.hidden) return 'HIDDEN';
  if (r.disabled) return 'EXEMPT (disabled control)';
  if (r.decorative) return 'DECORATIVE';
  if (r.ratio == null) return 'UNMEASURED';
  if (r.ratio + 1e-9 >= r.threshold) return informational ? 'INFO-PASS' : 'PASS';
  if (informational) return 'INFO-BELOW';
  const red = r.fg && Math.round(r.fg.r) === 215 && Math.round(r.fg.g) === 40 && Math.round(r.fg.b) === 47;
  const redNonText = r.value && r.value.r === 215 && r.value.g === 40 && r.value.b === 47;
  if (r.script && red) return 'BLOCKED — DESIGNER SIGN-OFF (script)';
  if (signoff === 'status' && redNonText) return 'BLOCKED — DESIGNER SIGN-OFF (status alpha rule)';
  return 'FAIL';
}

const fmt = (c) => (c ? normalised({ r: Math.round(c.r), g: Math.round(c.g), b: Math.round(c.b), a: Math.round((c.a ?? 1) * 1000) / 1000 }) : '');

/* ---------- run ---------- */
const server = await startServer(serveRoot);
const browser = await chromium.launch();
const decoder = await browser.newPage();
await decoder.setContent('<html><body></body></html>');

async function open(context, family) {
  const page = await context.newPage();
  await page.goto(server.origin + family.path, { waitUntil: 'networkidle' });
  await settle(page);
  await page.addStyleTag({ content: HIDE_CSS });
  await page.evaluate(installHelpers);
  return page;
}

async function sampleElement(page, index, fg, threshold) {
  const box = await page.evaluate((i) => {
    const el = window.__caEls[i];
    el.scrollIntoView({ block: 'center', inline: 'nearest' });
    const rs = window.__ca.textRects(el);
    if (!rs.length) return null;
    const l = Math.max(0, Math.min(...rs.map((r) => r.left)));
    const t = Math.max(0, Math.min(...rs.map((r) => r.top)));
    const rr = Math.min(innerWidth, Math.max(...rs.map((r) => r.right)));
    const b = Math.min(innerHeight, Math.max(...rs.map((r) => r.bottom)));
    if (rr - l < 2 || b - t < 2) return null;
    el.setAttribute('data-ca-hide', '');
    return { x: l, y: t, width: rr - l, height: b - t };
  }, index);
  if (!box) return null;
  const png = await page.screenshot({ clip: box, animations: 'disabled' });
  await page.evaluate((i) => window.__caEls[i].removeAttribute('data-ca-hide'), index);
  return decoder.evaluate(samplePixels, { b64: png.toString('base64'), fg, threshold });
}

const rows = [];
const stateRows = [];
for (const f of families) {
  for (const cell of CELLS) {
    const context = await newModeContext(browser, cell);
    const page = await open(context, f);
    await page.evaluate(() => window.__ca.collect());
    const items = await page.evaluate(() => window.__ca.analyseAll());
    for (const it of items) {
      if (it.complex && !it.hidden && !it.decorative) {
        it.sample = await sampleElement(page, it.i, it.fg, it.threshold);
        it.ratio = it.sample ? it.sample.p5 : null;
      }
      rows.push({ page: f.id, cell: cell.id, ...it, status: classify(it) });
    }
    await page.close();

    for (const sc of SCENARIOS.filter((s) => s.page === f.id && (!s.cells || s.cells.includes(cell.id)))) {
      const p = await open(context, f);
      let r;
      try {
        if (sc.prep) await sc.prep(p);
        if (sc.text) {
          r = await p.evaluate(([sel, opts]) => { window.__ca.collect(); const el = document.querySelector(sel); const i = window.__caEls.indexOf(el); const res = window.__ca.analyse(el, opts); return res ? { i, ...res } : { missing: true }; }, [sc.text, sc.opts || {}]);
          if (r && r.complex && r.i >= 0) { r.sample = await sampleElement(p, r.i, r.fg, r.threshold); r.ratio = r.sample ? r.sample.p5 : null; }
        } else if (sc.nonText) {
          r = await p.evaluate(([sel, prop]) => window.__ca.nonText(sel, prop), sc.nonText);
          if (r && !r.missing) { r.threshold = sc.min || 3; r.fg = r.value; if (r.style === 'none') { r.ratio = null; r.note = 'indicator style none'; } }
        } else if (sc.placeholder) {
          r = await p.evaluate((sel) => window.__ca.placeholder(sel), sc.placeholder);
        } else if (sc.chevron) {
          r = await p.evaluate((sel) => window.__ca.chevron(sel), sc.chevron);
          if (r && !r.missing) { r.threshold = 3; r.fg = r.value; }
        }
      } catch (e) {
        r = { missing: true, error: String(e).split('\n')[0] };
      }
      stateRows.push({ page: f.id, cell: cell.id, scenario: sc.name, ...r, status: classify(r || { missing: true }, sc) });
      await p.close();
    }
    await context.close();
    process.stdout.write(`  ${f.id}__${cell.id}: ${items.length} text elements\n`);
  }
}
await browser.close();
await server.close();

/* ---------- report ---------- */
const docDir = path.join(ROOT, 'docs/colour-migration');
fs.writeFileSync(path.join(docDir, `${reportName}.json`), JSON.stringify({ rows, stateRows }, null, 1));

const groups = new Map();
for (const r of rows) {
  if (['OFFSCREEN', 'OCCLUDED', 'HIDDEN'].includes(r.status)) continue;
  const bg = r.complex ? `sampled ${r.complex}` : fmt(r.bg);
  const key = [r.page, r.sel, fmt(r.fg), bg, r.threshold, r.status].join('|');
  const g = groups.get(key) || { page: r.page, sel: r.sel, text: r.text, fg: fmt(r.fg), bg, threshold: r.threshold, status: r.status, ratio: Infinity, cells: new Set(), n: 0, theme: r.theme, sample: null };
  g.n++;
  g.cells.add(r.cell);
  if (r.ratio != null && r.ratio < g.ratio) { g.ratio = r.ratio; g.sample = r.sample || null; g.text = r.text; }
  groups.set(key, g);
}
const grouped = [...groups.values()].sort((a, b) => (a.status === b.status ? a.ratio - b.ratio : a.status.localeCompare(b.status)));
const count = (list, s) => list.filter((x) => x.status === s).length;
const statuses = [...new Set([...rows, ...stateRows].map((r) => r.status))].sort();

let md = `# Contrast audit — brand-colour migration\n\nGenerated by \`scripts/contrast-audit.mjs\` (method in the script header). Pages: ${families.map((f) => f.id).join(', ')}; cells: ${CELLS.map((c) => c.id).join(', ')}.\n\n`;
md += `## Summary\n\n| Status | Text measurements | State / non-text checks |\n|---|---|---|\n`;
for (const s of statuses) md += `| ${s} | ${count(rows, s)} | ${count(stateRows, s)} |\n`;
md += `\nThresholds: 4.5:1 normal text, 3:1 large text (≥24px or ≥19px bold) and required non-text UI. Photo/gradient text is judged on the 5th-percentile ratio of the pixels actually behind the glyph box.\n`;

const table = (list) => `| Page | Element | Text | Theme | Cells | Fg | Backdrop | Req. | Worst ratio | Status |\n|---|---|---|---|---|---|---|---|---|---|\n` +
  list.map((g) => `| ${g.page} | \`${g.sel}\` | ${String(g.text).replace(/\|/g, '/')} | ${g.theme} | ${[...g.cells].join(', ')} | ${g.fg} | ${g.bg} | ${g.threshold} | ${Number.isFinite(g.ratio) ? g.ratio.toFixed(2) + (g.sample ? ` (p5; ${(100 * g.sample.under).toFixed(0)}% px below)` : '') : '—'} | ${g.status} |`).join('\n') + '\n';

const fails = grouped.filter((g) => g.status === 'FAIL');
const blocked = grouped.filter((g) => g.status.startsWith('BLOCKED'));
md += `\n## Failures (text)\n\n${fails.length ? table(fails) : '_None._\n'}`;
md += `\n## Blocked — designer sign-off (text)\n\n${blocked.length ? table(blocked) : '_None._\n'}`;
md += `\n## Text over photography / gradients (pixel-sampled)\n\n${table(grouped.filter((g) => g.bg.startsWith('sampled')))}`;
md += `\n## States and non-text UI\n\n| Page | Cell | Check | Element | Fg / indicator | Backdrop | Req. | Ratio | Status |\n|---|---|---|---|---|---|---|---|---|\n`;
for (const r of stateRows) {
  md += `| ${r.page} | ${r.cell} | ${r.scenario} | \`${r.sel || ''}\` | ${fmt(r.fg)} | ${r.complex ? 'sampled ' + r.complex : fmt(r.bg)} | ${r.threshold || ''} | ${r.ratio != null ? r.ratio.toFixed(2) + (r.sample ? ' (p5)' : '') : r.note || r.error || '—'} | ${r.status} |\n`;
}
md += `\n## All measured text (grouped)\n\n${table(grouped)}`;
fs.writeFileSync(path.join(docDir, `${reportName}.md`), md);

const stateFails = stateRows.filter((r) => r.status === 'FAIL');
console.log(`\ntext measurements: ${rows.length}; grouped: ${grouped.length}`);
for (const s of statuses) console.log(`  ${s}: text ${count(rows, s)}, states ${count(stateRows, s)}`);
for (const g of fails) console.log(`  FAIL ${g.page} [${[...g.cells].join(',')}] ${g.sel} "${g.text}" fg ${g.fg} on ${g.bg} = ${g.ratio.toFixed(2)} (needs ${g.threshold})`);
for (const r of stateFails) console.log(`  FAIL state ${r.page} ${r.cell} ${r.scenario}: ${r.sel} ${fmt(r.fg)} on ${fmt(r.bg)} = ${r.ratio != null ? r.ratio.toFixed(2) : '—'}`);
for (const g of blocked) console.log(`  BLOCKED ${g.page} [${[...g.cells].join(',')}] ${g.sel} "${g.text}" = ${g.ratio.toFixed(2)}`);
for (const r of stateRows.filter((x) => x.status === 'MISSING')) console.log(`  MISSING state ${r.page} ${r.cell} ${r.scenario} ${r.error || ''}`);
process.exit(fails.length || stateFails.length ? 1 : 0);
