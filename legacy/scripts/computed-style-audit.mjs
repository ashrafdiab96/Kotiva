#!/usr/bin/env node
/* ============================================================
   Computed-style verification for the brand-colour migration (brief §6.9, §6.9b, §6.10).

   1. Sweep: every rendered element AND its ::before/::after on every page family x
      {desktop, 390} x {light, dark}: computed color (text/SVG), background-color, border colours,
      outline, text-decoration, SVG fill/stroke, and every colour inside box-shadow, text-shadow,
      filter and background-image. Each value must be an approved base colour with an approved
      alpha (the same rules as the source validator, scripts/lib/palette-rules.mjs).
   2. Theme x mode matrix: a test fixture renders the same components inside ritual, editorial
      and clinical sections in light and dark mode; resolved tokens are compared to the expected
      table (catches Ritual values leaking into Editorial/Clinical/Dark) and component colours,
      including :hover and the focused skip link, are recorded.
   3. Nav / logo surface check on real pages (transparent vs scrolled, light vs dark).
   4. Mode initialisation: backgrounds observed before and after the persisted mode applies.
   5. Static custom-property dependency resolution in css/kotiva.css (var() chains + fallbacks),
      undefined-token report, and --k-* token usage.

   Usage: node scripts/computed-style-audit.mjs
   Writes docs/colour-migration/computed-style-audit.{json,md}; exit 1 on any violation.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { startServer } from './lib/static-server.mjs';
import { PAGE_FAMILIES, CELLS, newModeContext, settle } from './lib/pages.mjs';
import { parseCssColour, normalised, extractColours } from './lib/colour-literals.mjs';
import { loadPaletteRules } from './lib/palette-rules.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const verdict = loadPaletteRules(ROOT);
const docDir = path.join(ROOT, 'docs/colour-migration');

/* Expected resolved tokens per theme x mode (brief §4.1–4.4 plus the verified role changes recorded
   in docs/COLOUR-MIGRATION.md Part B). Dark mode does not override editorial or clinical sections. */
const RITUAL = { '--bg': '#FFFFFF', '--bg-alt': 'rgba(138,183,233,0.18)', '--bg-card': 'rgba(138,183,233,0.1)', '--fg': '#231F20', '--fg-mid': 'rgba(35,31,32,0.72)', '--fg-dim': 'rgba(35,31,32,0.62)', '--accent': '#8AB7E9', '--accent-light': '#8AB7E9', '--accent-deep': '#005DBA', '--accent-text': '#005DBA', '--border': 'rgba(35,31,32,0.12)', '--border-strong': 'rgba(35,31,32,0.3)', '--script': '#D7282F' };
const EDITORIAL = { '--bg': '#6A3277', '--bg-alt': '#6A3277', '--bg-card': 'rgba(255,255,255,0.07)', '--fg': '#FFFFFF', '--fg-mid': 'rgba(255,255,255,0.78)', '--fg-dim': 'rgba(255,255,255,0.64)', '--accent': '#8AB7E9', '--accent-light': '#8AB7E9', '--accent-deep': '#FFFFFF', '--accent-text': '#FFFFFF', '--border': 'rgba(255,255,255,0.18)', '--border-strong': 'rgba(255,255,255,0.4)', '--script': '#D7282F' };
const CLINICAL = { '--bg': '#FFFFFF', '--bg-alt': 'rgba(138,183,233,0.18)', '--bg-card': 'rgba(138,183,233,0.1)', '--fg': '#231F20', '--fg-mid': 'rgba(35,31,32,0.72)', '--fg-dim': 'rgba(35,31,32,0.62)', '--accent': '#005DBA', '--accent-light': '#005DBA', '--accent-deep': '#005DBA', '--accent-text': '#005DBA', '--border': 'rgba(0,93,186,0.15)', '--border-strong': 'rgba(0,93,186,0.4)', '--script': '#D7282F' };
const DARK = { '--bg': '#231F20', '--bg-alt': 'rgba(255,255,255,0.04)', '--bg-card': 'rgba(255,255,255,0.07)', '--fg': '#FFFFFF', '--fg-mid': 'rgba(255,255,255,0.78)', '--fg-dim': 'rgba(255,255,255,0.64)', '--accent': '#8AB7E9', '--accent-light': '#8AB7E9', '--accent-deep': '#FFFFFF', '--accent-text': '#8AB7E9', '--border': 'rgba(255,255,255,0.18)', '--border-strong': 'rgba(255,255,255,0.4)', '--script': '#D7282F' };
const EXPECTED = {
  light: { root: RITUAL, ritual: RITUAL, editorial: EDITORIAL, clinical: CLINICAL },
  dark: { root: DARK, ritual: DARK, editorial: EDITORIAL, clinical: CLINICAL },
};
export const EXPECTED_TOKENS = EXPECTED;

const FIXTURE_COMPONENTS = `
  <div class="container fx">
    <p class="t-eyebrow">Eyebrow</p>
    <span class="t-script">Script accent</span>
    <a class="btn btn-gold" href="#p">Primary</a>
    <a class="btn btn-outline" href="#s">Secondary</a>
    <a class="btn btn-text" href="#t">Text link</a>
    <div class="product-card"><div class="product-card-body"><div class="product-card-name">Card</div>
      <div class="product-card-footer"><span class="badge-concern">Hydration</span><span class="product-card-cta">Discover</span></div></div></div>
    <div class="filter-pills"><button class="filter-pill">Inactive</button><button class="filter-pill active">Active</button></div>
    <span class="credential-badge">Credential</span>
    <footer class="footer"><div class="footer-bottom">Footer</div></footer>
  </div>`;
const FIXTURE = `<!doctype html><html lang="en" data-theme="ritual"><head><meta charset="utf-8">
<link rel="stylesheet" href="/css/kotiva.css?v=fixture"></head><body>
<a class="skip-link" href="#main">Skip to content</a>
<section id="fx-ritual" data-theme="ritual">${FIXTURE_COMPONENTS}</section>
<section id="fx-editorial" data-theme="editorial">${FIXTURE_COMPONENTS}</section>
<section id="fx-clinical" data-theme="clinical">${FIXTURE_COMPONENTS}</section>
</body></html>`;

/* ---------- in-page sweep ---------- */
function sweep() {
  const out = [];
  const colours = (s) => (s && s.match(/rgba?\([^)]*\)/g)) || [];
  const name = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + [...el.classList].slice(0, 3).map((c) => '.' + c).join('');
  const SHAPES = new Set(['path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'use', 'text']);
  for (const el of document.querySelectorAll('body, body *')) {
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE'].includes(el.tagName)) continue;
    const tag = el.tagName.toLowerCase();
    for (const pseudo of [null, '::before', '::after']) {
      const cs = getComputedStyle(el, pseudo);
      if (pseudo) { if (cs.content === 'none' || cs.content === 'normal') continue; }
      else if (!el.getClientRects().length) continue;
      if (cs.display === 'none') continue;
      const where = name(el) + (pseudo || '');
      const push = (prop, value, shadow = false) => out.push({ where, prop, value, shadow });
      const ownText = !pseudo && [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
      const pseudoText = !!pseudo && cs.content !== '""' && cs.content !== "''";
      if (ownText || pseudoText || tag === 'svg' || SHAPES.has(tag)) push('color', cs.color);
      if (colours(cs.backgroundColor).length) push('background-color', cs.backgroundColor);
      for (const side of ['Top', 'Right', 'Bottom', 'Left']) {
        const st = cs['border' + side + 'Style'];
        if (st !== 'none' && st !== 'hidden' && parseFloat(cs['border' + side + 'Width']) > 0) push('border-' + side.toLowerCase() + '-color', cs['border' + side + 'Color']);
      }
      if (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) push('outline-color', cs.outlineColor);
      if ((ownText || pseudoText) && cs.textDecorationLine && cs.textDecorationLine !== 'none') push('text-decoration-color', cs.textDecorationColor);
      if (SHAPES.has(tag)) for (const p of ['fill', 'stroke']) { const v = cs[p]; if (v && v !== 'none' && !v.startsWith('url')) push(p, v); }
      for (const c of colours(cs.boxShadow)) push('box-shadow', c, true);
      for (const c of colours(cs.textShadow)) push('text-shadow', c, true);
      for (const c of colours(cs.filter)) push('filter', c, true);
      if (cs.backgroundImage !== 'none') for (const c of colours(cs.backgroundImage)) push('background-image', c);
    }
  }
  return out;
}

/* Pre-existing, NOT introduced by the migration and deliberately not fixed (brief §5 "no auto-fix
   outside scope"): `.btn-primary` has no CSS rule anywhere in the repository (before or after), so the
   routine-finder "Next" <button>s render the browser's own button text colour — UA `buttontext`, and
   the UA :disabled grey. These are user-agent defaults, not author colours; they are reported in their
   own section of the report instead of being counted as migration violations. */
const PRE_EXISTING = [
  { where: /^button#next-\d+\.btn\.btn-primary$/, prop: 'color', values: [/^rgba\(16, 16, 16, 0\.3\)$/, /^rgb\(0, 0, 0\)$/], note: 'UA default text colour on the unstyled .btn-primary button (no author rule exists)' },
];
const preExisting = [];

const server = await startServer(ROOT);
const browser = await chromium.launch();
const violations = [];
const seenValues = new Map(); // normalised -> count

/* 1. sweep real pages */
const sweepSummary = [];
for (const f of PAGE_FAMILIES) {
  for (const cell of CELLS) {
    const context = await newModeContext(browser, cell);
    const page = await context.newPage();
    await page.goto(server.origin + f.path, { waitUntil: 'networkidle' });
    await settle(page);
    const entries = await page.evaluate(sweep);
    let bad = 0;
    for (const e of entries) {
      const c = parseCssColour(e.value);
      const v = verdict({ kind: 'function', raw: e.value, colour: c, shadow: e.shadow });
      if (c && c.a > 0) seenValues.set(normalised(c), (seenValues.get(normalised(c)) || 0) + 1);
      const known = v && PRE_EXISTING.find((p) => p.where.test(e.where) && p.prop === e.prop && p.values.some((re) => re.test(e.value)));
      if (known) { preExisting.push({ page: f.id, cell: cell.id, where: e.where, prop: e.prop, value: e.value, note: known.note }); continue; }
      if (v) { bad++; violations.push({ section: 'sweep', page: f.id, cell: cell.id, where: e.where, prop: e.prop, value: e.value, reason: v }); }
    }
    sweepSummary.push({ page: f.id, cell: cell.id, values: entries.length, violations: bad });
    await context.close();
    process.stdout.write(`  sweep ${f.id}__${cell.id}: ${entries.length} colour values, ${bad} violation(s)\n`);
  }
}

/* 2. theme x mode fixture */
const matrix = [];
const tokenRows = [];
for (const mode of ['light', 'dark']) {
  const context = await newModeContext(browser, { viewport: { width: 1280, height: 900 }, mode });
  const page = await context.newPage();
  await page.route(server.origin + '/__colour-fixture.html', (route) => route.fulfill({ contentType: 'text/html', body: FIXTURE }));
  await page.goto(server.origin + '/__colour-fixture.html', { waitUntil: 'networkidle' });
  await page.evaluate((m) => { if (m === 'dark') document.documentElement.dataset.mode = 'dark'; }, mode);
  await page.waitForTimeout(700); // [data-theme] background transition

  const tokenNames = Object.keys(RITUAL);
  for (const theme of ['root', 'ritual', 'editorial', 'clinical']) {
    const sel = theme === 'root' ? 'html' : `#fx-${theme}`;
    const got = await page.evaluate(([s, names]) => { const cs = getComputedStyle(document.querySelector(s)); return Object.fromEntries(names.map((n) => [n, cs.getPropertyValue(n).trim()])); }, [sel, tokenNames]);
    for (const n of tokenNames) {
      const exp = EXPECTED[mode][theme][n];
      const gc = parseCssColour(got[n]);
      const ec = parseCssColour(exp);
      const ok = gc && ec && normalised(gc) === normalised(ec);
      tokenRows.push({ mode, theme, token: n, expected: exp, got: got[n], ok });
      if (!ok) violations.push({ section: 'tokens', page: 'fixture', cell: mode, where: `${theme} ${n}`, prop: 'custom property', value: got[n], reason: `expected ${exp}` });
    }
  }

  const COMPONENTS = [
    ['section', '', ['backgroundColor', 'color']],
    ['.t-eyebrow', '', ['color']],
    ['.t-script', '', ['color']],
    ['.btn-gold', '', ['backgroundColor', 'color']],
    ['.btn-gold', ':hover', ['backgroundColor', 'color']],
    ['.btn-outline', '', ['borderTopColor', 'color']],
    ['.btn-text', '', ['color']],
    ['.product-card', '', ['backgroundColor', 'borderTopColor']],
    ['.badge-concern', '', ['color', 'borderTopColor']],
    ['.product-card-cta', '::after', ['color']],
    ['.filter-pill:not(.active)', '', ['color', 'borderTopColor', 'backgroundColor']],
    ['.filter-pill.active', '', ['color', 'borderTopColor', 'backgroundColor']],
    ['.credential-badge', '::before', ['borderLeftColor']],
    ['.footer', '', ['backgroundColor', 'borderTopColor']],
    ['.footer-bottom', '', ['color']],
  ];
  for (const theme of ['ritual', 'editorial', 'clinical']) {
    for (const [sel, state, props] of COMPONENTS) {
      const full = sel === 'section' ? `#fx-${theme}` : `#fx-${theme} ${sel}`;
      if (state === ':hover') { await page.hover(full); await page.waitForTimeout(450); }
      const vals = await page.evaluate(([s, pseudo, ps]) => { const el = document.querySelector(s); const cs = getComputedStyle(el, pseudo === '::after' || pseudo === '::before' ? pseudo : null); return ps.map((p) => cs[p]); }, [full, state, props]);
      if (state === ':hover') await page.mouse.move(0, 0);
      props.forEach((p, i) => {
        const c = parseCssColour(vals[i]);
        const v = verdict({ kind: 'function', raw: vals[i], colour: c, shadow: false });
        matrix.push({ mode, theme, component: sel + (state || ''), prop: p, value: c ? normalised(c) : vals[i], ok: !v });
        if (v) violations.push({ section: 'matrix', page: 'fixture', cell: mode, where: `${theme} ${sel}${state}`, prop: p, value: vals[i], reason: v });
      });
    }
  }
  await page.mouse.move(0, 0);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.keyboard.press('Tab');
  await page.waitForTimeout(350);
  const skip = await page.evaluate(() => { const el = document.querySelector('.skip-link'); const cs = getComputedStyle(el); return { focused: document.activeElement === el, bg: cs.backgroundColor, color: cs.color, top: el.getBoundingClientRect().top }; });
  for (const [p, val] of [['backgroundColor', skip.bg], ['color', skip.color]]) {
    const c = parseCssColour(val);
    matrix.push({ mode, theme: 'root', component: '.skip-link:focus', prop: p, value: c ? normalised(c) : val, ok: skip.focused && !verdict({ kind: 'function', raw: val, colour: c }) });
  }
  if (!skip.focused || skip.top < 0) violations.push({ section: 'matrix', page: 'fixture', cell: mode, where: '.skip-link', prop: 'focus', value: JSON.stringify(skip), reason: 'skip link not focused/visible on first Tab' });
  await context.close();
}

/* 3. nav / logo surface on real pages */
const navRows = [];
for (const f of PAGE_FAMILIES.filter((x) => ['home', 'shop', 'about', 'product'].includes(x.id))) {
  for (const cell of CELLS.filter((c) => c.id.startsWith('desktop'))) {
    const context = await newModeContext(browser, cell);
    const page = await context.newPage();
    await page.goto(server.origin + f.path, { waitUntil: 'networkidle' });
    await settle(page);
    const read = () => page.evaluate(() => {
      const nav = document.querySelector('.nav');
      const cs = getComputedStyle(nav);
      const vis = (s) => { const el = nav.querySelector(s); return el ? getComputedStyle(el).display !== 'none' : null; };
      const link = nav.querySelector('.nav-links .nav-link');
      return { state: nav.classList.contains('scrolled') ? 'scrolled' : 'transparent', bg: cs.backgroundColor, link: link ? getComputedStyle(link).color : '', darkLogo: vis('.logo-dark'), lightLogo: vis('.logo-light') };
    });
    const states = [await read()];
    await page.evaluate(() => window.scrollTo(0, 700));
    await page.waitForTimeout(200);
    await page.evaluate(() => window.scrollTo(0, 640));
    await page.waitForTimeout(900); // let the nav's colour/transform transitions finish
    states.push(await read());
    for (const s of states) {
      const dark = cell.mode === 'dark';
      const expectLight = s.state === 'transparent' || dark;
      const ok = s.lightLogo === expectLight && s.darkLogo === !expectLight;
      navRows.push({ page: f.id, mode: cell.mode, ...s, expectedLogo: expectLight ? 'white' : 'black', ok });
      if (!ok) violations.push({ section: 'nav-logo', page: f.id, cell: cell.id, where: `.nav (${s.state})`, prop: 'logo variant', value: `dark:${s.darkLogo} light:${s.lightLogo}`, reason: `expected ${expectLight ? 'white' : 'black'} logo` });
      for (const [p, val] of [['nav background', s.bg], ['nav link', s.link]]) {
        const c = parseCssColour(val);
        const v = verdict({ kind: 'function', raw: val, colour: c });
        if (v) violations.push({ section: 'nav-logo', page: f.id, cell: cell.id, where: `.nav (${s.state})`, prop: p, value: val, reason: v });
      }
    }
    await context.close();
  }
}

/* 4. mode initialisation */
const initRows = [];
for (const f of PAGE_FAMILIES.filter((x) => ['home', 'about', 'product'].includes(x.id))) {
  for (const mode of ['light', 'dark']) {
    const context = await newModeContext(browser, { viewport: { width: 1280, height: 800 }, mode });
    await context.addInitScript(() => {
      const rec = (window.__modeInit = []);
      // Init scripts run before <html> exists, so every read is null-safe and the observer watches `document`.
      const snap = (label) => {
        const de = document.documentElement;
        if (!de) return;
        rec.push({ label, mode: de.dataset.mode || 'light', html: getComputedStyle(de).backgroundColor, body: document.body ? getComputedStyle(document.body).backgroundColor : null, bgToken: getComputedStyle(de).getPropertyValue('--bg').trim() });
      };
      const mo = new MutationObserver(() => { if (document.body && !rec.some((r) => r.label === 'body-parsed')) { snap('body-parsed'); mo.disconnect(); } });
      mo.observe(document, { childList: true, subtree: true });
      requestAnimationFrame(() => snap('first-frame'));
      document.addEventListener('DOMContentLoaded', () => snap('DOMContentLoaded'));
      window.addEventListener('load', () => snap('load'));
    });
    const page = await context.newPage();
    await page.goto(server.origin + f.path, { waitUntil: 'networkidle' });
    const rec = await page.evaluate(() => window.__modeInit);
    for (const r of rec) {
      initRows.push({ page: f.id, persisted: mode, ...r });
      for (const val of [r.html, r.body, r.bgToken]) {
        const c = parseCssColour(val);
        if (!val || !c) continue;
        const v = verdict({ kind: 'function', raw: val, colour: c });
        if (v) violations.push({ section: 'mode-init', page: f.id, cell: mode, where: r.label, prop: 'background', value: val, reason: v });
      }
    }
    await context.close();
  }
}
await browser.close();
await server.close();

/* 5. static custom-property dependency resolution */
const cssText = fs.readFileSync(path.join(ROOT, 'css/kotiva.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const decls = []; // {selector, name, value}
{
  const stack = [];
  let buf = '';
  for (const ch of cssText) {
    if (ch === '{') { stack.push(buf.trim()); buf = ''; }
    else if (ch === '}') { const m = buf.match(/(--[\w-]+)\s*:\s*([^;]+)$/); if (m) decls.push({ selector: stack[stack.length - 1], name: m[1], value: m[2].trim() }); stack.pop(); buf = ''; }
    else if (ch === ';') { const m = buf.match(/(--[\w-]+)\s*:\s*([\s\S]+)$/); if (m) decls.push({ selector: stack[stack.length - 1], name: m[1], value: m[2].trim() }); buf = ''; }
    else buf += ch;
  }
}
const htmlTexts = fs.readdirSync(ROOT).filter((n) => n.endsWith('.html')).map((n) => fs.readFileSync(path.join(ROOT, n), 'utf8'))
  .concat(['scripts/generate-product-pages.js', 'scripts/generate-ingredients-page.js', 'js/layout.js', 'js/animations.js'].map((p) => fs.readFileSync(path.join(ROOT, p), 'utf8')));
const allUsage = [cssText, ...htmlTexts].join('\n');
const declared = new Set(decls.map((d) => d.name));
const rootScopes = [':root', '[data-theme="ritual"], html'];
const resolveIn = (selector, value, depth = 0) => {
  if (depth > 12) return { value, unresolved: ['(cycle)'] };
  const unresolved = [];
  const out = value.replace(/var\(\s*(--[\w-]+)\s*(?:,\s*([^()]*(?:\([^()]*\)[^()]*)*))?\)/g, (m, n, fb) => {
    const own = decls.filter((d) => d.name === n && d.selector === selector).pop() || decls.filter((d) => d.name === n && rootScopes.includes(d.selector)).pop();
    if (own) { const r = resolveIn(selector, own.value, depth + 1); unresolved.push(...r.unresolved); return r.value; }
    if (fb !== undefined) return resolveIn(selector, fb, depth + 1).value;
    unresolved.push(n);
    return m;
  });
  return { value: out, unresolved };
};
const staticRows = [];
for (const d of decls) {
  const r = resolveIn(d.selector, d.value);
  const lits = extractColours(r.value, '.css');
  const bad = lits.map((h) => ({ h, v: verdict({ ...h, shadow: /^--shadow/.test(d.name) }) })).filter((x) => x.v);
  if (lits.length || r.unresolved.length) staticRows.push({ selector: d.selector, token: d.name, value: d.value, resolved: r.value, unresolved: r.unresolved, ok: !bad.length });
  for (const b of bad) violations.push({ section: 'static-tokens', page: 'css/kotiva.css', cell: '', where: `${d.selector} ${d.name}`, prop: 'resolved value', value: r.value, reason: b.v });
}
const referenced = new Set([...allUsage.matchAll(/var\(\s*(--[\w-]+)/g)].map((m) => m[1]));
const inlineDeclared = new Set([...allUsage.matchAll(/(--[\w-]+)\s*:/g)].map((m) => m[1]));
const undefinedTokens = [...referenced].filter((n) => !declared.has(n) && !inlineDeclared.has(n)).sort();
const kTokens = decls.filter((d) => d.name.startsWith('--k-')).map((d) => ({ token: d.name, value: d.value, references: [...allUsage.matchAll(new RegExp(`var\\(\\s*${d.name}\\b`, 'g'))].length }));

/* ---------- report ---------- */
fs.writeFileSync(path.join(docDir, 'computed-style-audit.json'), JSON.stringify({ sweepSummary, violations, preExisting, tokenRows, matrix, navRows, initRows, staticRows, undefinedTokens, kTokens, seenValues: Object.fromEntries(seenValues) }, null, 1));
let md = `# Computed-style audit — brand-colour migration\n\nGenerated by \`scripts/computed-style-audit.mjs\` (method in the script header).\n\n`;
md += `**Violations: ${violations.length}.**\n\n## 1. Rendered colour sweep (all elements + ::before/::after)\n\n| Page | Cell | Colour values checked | Violations |\n|---|---|---|---|\n`;
md += sweepSummary.map((s) => `| ${s.page} | ${s.cell} | ${s.values} | ${s.violations} |`).join('\n') + '\n';
md += `\nDistinct computed colour values observed (all pages/cells): ${[...seenValues.keys()].sort().map((v) => `\`${v}\``).join(', ')}\n`;
md += `\n## 2. Theme × mode matrix (fixture)\n\n### Resolved tokens\n\n| Mode | Scope | Token | Expected | Resolved | OK |\n|---|---|---|---|---|---|\n`;
md += tokenRows.map((r) => `| ${r.mode} | ${r.theme} | \`${r.token}\` | ${r.expected} | ${r.got} | ${r.ok ? 'yes' : '**NO**'} |`).join('\n') + '\n';
md += `\n### Components\n\n| Mode | Theme | Component | Property | Computed | Approved |\n|---|---|---|---|---|---|\n`;
md += matrix.map((r) => `| ${r.mode} | ${r.theme} | \`${r.component}\` | ${r.prop} | ${r.value} | ${r.ok ? 'yes' : '**NO**'} |`).join('\n') + '\n';
md += `\n## 3. Nav surface and logo variant\n\n| Page | Mode | Nav state | Nav background | Link colour | Logo shown | Expected | OK |\n|---|---|---|---|---|---|---|---|\n`;
md += navRows.map((r) => `| ${r.page} | ${r.mode} | ${r.state} | ${r.bg} | ${r.link} | ${r.lightLogo ? 'white' : r.darkLogo ? 'black' : '—'} | ${r.expectedLogo} | ${r.ok ? 'yes' : '**NO**'} |`).join('\n') + '\n';
md += `\n## 4. Mode initialisation\n\n| Page | Persisted mode | Moment | html[data-mode] | html background | body background | --bg |\n|---|---|---|---|---|---|---|\n`;
md += initRows.map((r) => `| ${r.page} | ${r.persisted} | ${r.label} | ${r.mode} | ${r.html} | ${r.body || '—'} | ${r.bgToken} |`).join('\n') + '\n';
md += `\n## 5. Static custom-property resolution (css/kotiva.css)\n\n| Selector | Token | Declared | Resolved | Unresolved refs | OK |\n|---|---|---|---|---|---|\n`;
md += staticRows.map((r) => `| \`${r.selector}\` | \`${r.token}\` | \`${r.value}\` | \`${r.resolved}\` | ${r.unresolved.join(', ') || '—'} | ${r.ok ? 'yes' : '**NO**'} |`).join('\n') + '\n';
md += `\nTokens referenced but never declared (pre-existing, no colour literal involved): ${undefinedTokens.map((t) => `\`${t}\``).join(', ') || 'none'}\n`;
md += `\n### --k-* palette tokens\n\n| Token | Value | var() references |\n|---|---|---|\n` + kTokens.map((k) => `| \`${k.token}\` | ${k.value} | ${k.references} |`).join('\n') + '\n';
md += `\n## Pre-existing (not introduced by the migration, not fixed — brief §5)\n\n` + (preExisting.length
  ? `| Page | Cell | Where | Property | Value | Note |\n|---|---|---|---|---|---|\n` + preExisting.map((p) => `| ${p.page} | ${p.cell} | \`${p.where}\` | ${p.prop} | ${p.value} | ${p.note} |`).join('\n') + '\n'
  : '_None observed._\n');
if (violations.length) md += `\n## Violations\n\n| Section | Page | Cell | Where | Property | Value | Reason |\n|---|---|---|---|---|---|---|\n` + violations.map((v) => `| ${v.section} | ${v.page} | ${v.cell} | \`${v.where}\` | ${v.prop} | ${v.value} | ${v.reason} |`).join('\n') + '\n';
fs.writeFileSync(path.join(docDir, 'computed-style-audit.md'), md);

console.log(`\ncomputed-style audit: ${violations.length} violation(s)`);
const agg = new Map();
for (const v of violations) { const k = `${v.section} | ${v.where} | ${v.prop} | ${v.value} | ${v.reason}`; agg.set(k, (agg.get(k) || []).concat(`${v.page}/${v.cell}`)); }
for (const [k, where] of agg) console.log(`  ${k}  [${where.slice(0, 6).join(', ')}${where.length > 6 ? ` +${where.length - 6}` : ''}]`);
console.log(`undefined tokens: ${undefinedTokens.join(', ') || 'none'}`);
process.exit(violations.length ? 1 : 0);
