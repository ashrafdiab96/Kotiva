#!/usr/bin/env node
/**
 * check-global-consistency.js — do the global components look and behave the SAME on every page?
 *
 * WHY THIS EXISTS
 * ---------------
 * The 2026-08-19 gate passed every technical leg — axe 0, links 43/0, overflow 0, Lighthouse
 * A11y 100 — and a thirty-second human review immediately found two global defects it could not
 * see:
 *
 *   1. The footer logo was a LINK on index.html and a bare <img> on the other eight page families.
 *      Every page had a footer, every footer had a logo, no link was broken. Nothing in the gate
 *      compares one page's chrome against another's, so nothing looked.
 *   2. The current-page indicator was applied on every page and rendered NOTHING anywhere (an
 *      inherited `transform: scaleX(0)` from the pre-existing hover underline). The gate asserted
 *      the CLASS was present. It was present. It was also invisible.
 *
 * Both are the same class of miss: the gate proved per-page technical properties and never asked
 * "is this the same everywhere, and can a person actually see it?"
 *
 * WHAT THIS CHECKS — and its honest limit
 * ---------------------------------------
 * This file is the STATIC half: it compares the header and footer of every page family against a
 * reference page and fails on any structural divergence. It is deliberately cheap and runs in
 * `npm test` on every commit.
 *
 * It CANNOT tell you whether something is visible. A rule with `transform: scaleX(0)` is present
 * in the DOM and invisible on screen, which is exactly defect 2 above. Visibility is proved by the
 * rendered leg in 70_tooling/qa_gate.mjs (`--consistency`), which measures computed styles in a
 * real browser across nav states. Neither half is sufficient alone, and this comment exists so
 * nobody mistakes a green run here for "the chrome is fine".
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const fail = [];
const ok = [];

/** Every distinct page family. A generator's output is represented by one sample: if the
 *  generator is wrong all 25 are wrong, and if it is right one proves it. */
const FAMILIES = [
  'index.html', 'shop.html', 'about.html', 'science.html', 'journal.html',
  'contact.html', 'routine-finder.html', 'ingredients.html', 'product.html',
  'privacy-policy.html', 'terms.html', 'product/micellar-water.html',
];
const REFERENCE = 'index.html';

const read = (f) => fs.readFileSync(path.join(ROOT, f), 'utf8');
const strip = (s) => s.replace(/<!--[\s\S]*?-->/g, '');
const section = (html, tag, cls) => {
  const re = new RegExp(`<${tag}[^>]*class="[^"]*${cls}[^"]*"[\\s\\S]*?</${tag}>`, 'i');
  const m = strip(html).match(re);
  return m ? m[0] : null;
};

/** Links as (label -> destination), with the leading "/" normalised away: the generators emit
 *  absolute paths because product pages live one directory down, and that is correct, not drift. */
function links(fragment) {
  const out = [];
  const re = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = re.exec(fragment))) {
    const attrs = m[1];
    const href = (attrs.match(/href="([^"]*)"/) || [])[1] || '';
    const label = m[2].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    out.push({ href: href.replace(/^\//, ''), label, hasImg: /<img/i.test(m[2]) });
  }
  return out;
}

function compare(name, extract, describe) {
  const ref = extract(read(REFERENCE));
  if (!ref) { fail.push(`[${name}] reference page ${REFERENCE} has no ${name} to compare against`); return; }
  const refSig = describe(ref);
  let bad = 0;
  for (const f of FAMILIES) {
    if (f === REFERENCE) continue;
    let html;
    try { html = read(f); } catch { continue; }
    const frag = extract(html);
    if (!frag) { fail.push(`[${name}] ${f} has no ${name} at all`); bad++; continue; }
    const sig = describe(frag);
    for (const key of Object.keys(refSig)) {
      const a = JSON.stringify(refSig[key]);
      const b = JSON.stringify(sig[key]);
      if (a !== b) {
        fail.push(`[${name}] ${f} differs from ${REFERENCE} on "${key}"\n         ${REFERENCE}: ${a}\n         ${f}: ${b}`);
        bad++;
      }
    }
  }
  if (!bad) ok.push(`[${name}] identical across ${FAMILIES.length} page families`);
}

/* ---- HEADER --------------------------------------------------------------------------- */
compare('header',
  (h) => section(h, 'nav', 'nav'),
  (frag) => {
    const all = links(frag);
    const navLinks = all.filter((l) => !l.hasImg && l.label);
    const logo = all.find((l) => l.hasImg);
    return {
      'nav destinations + order': navLinks.map((l) => `${l.label}->${l.href}`),
      'logo is a link': !!logo,
      'logo destination': logo ? logo.href : null,
      'CTA present': /btn-gold/.test(frag),
      'theme toggle present': /theme-toggle/.test(frag),
      'hamburger present': /nav-hamburger/.test(frag),
    };
  });

/* ---- MOBILE OVERLAY ------------------------------------------------------------------- */
compare('mobile menu',
  (h) => section(h, 'div', 'nav-overlay'),
  (frag) => ({
    'overlay destinations + order': links(frag).filter((l) => l.label).map((l) => `${l.label}->${l.href}`),
  }));

/* ---- FOOTER --------------------------------------------------------------------------- */
compare('footer',
  (h) => section(h, 'footer', 'footer'),
  (frag) => {
    const all = links(frag);
    const logo = all.find((l) => l.hasImg);
    return {
      // This is the check that would have caught the index-only footer-brand-link.
      'brand logo is a link': !!logo,
      'brand logo destination': logo ? logo.href : null,
      'footer destinations + order': all.filter((l) => l.label).map((l) => `${l.label}->${l.href}`),
    };
  });

/* ---- ONE DESTINATION, ONE LABEL ------------------------------------------------------- */
{
  const frag = section(read(REFERENCE), 'footer', 'footer');
  const byHref = new Map();
  for (const l of links(frag)) {
    if (!l.label || l.hasImg) continue;
    // Full href INCLUDING the fragment: contact.html and contact.html#faq are genuinely
    // different destinations (the page and its FAQ section), and collapsing them reported a
    // real duplicate alongside a false one.
    const key = l.href;
    if (!key) continue;
    if (!byHref.has(key)) byHref.set(key, new Set());
    byHref.get(key).add(l.label);
  }
  let dup = 0;
  for (const [href, labels] of byHref) {
    if (labels.size > 1) {
      fail.push(`[footer labels] one destination "${href}" carries ${labels.size} different labels: ${[...labels].join(' / ')}`);
      dup++;
    }
  }
  if (!dup) ok.push('[footer labels] every destination has exactly one label');
}

/* ---- REPORT --------------------------------------------------------------------------- */
console.log('\nKOTIVA global consistency gate — scripts/check-global-consistency.js');
ok.forEach((l) => console.log(`  OK   ${l}`));
fail.forEach((l) => console.log(`  FAIL ${l}`));
if (fail.length) {
  console.log(`\nFAIL — ${fail.length} global-chrome inconsistency(ies).`);
  console.log('Fix the SOURCE (root page templates + scripts/generate-*.js), never the emitted pages.');
  process.exit(1);
}
console.log('\nPASS — header, mobile menu and footer are identical across every page family.');
console.log('NOTE: this proves STRUCTURE, not visibility. A control can be present and render');
console.log('nothing (transform:scaleX(0) did exactly that). Visibility is the qa_gate.mjs leg.');
