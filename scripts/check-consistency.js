#!/usr/bin/env node
/* ============================================================
   KOTIVA — Repo Consistency Gate (Wave E mechanism)
   Zero-dependency CI check that makes silent drift LOUD. Fails
   (exit 1) with explicit messages when any of these recurring
   defect classes reappears:

   (a) Version-token drift (DEC-190 class): every `?v=` reference
       to a core asset (kotiva.css, layout.js, animations.js,
       data-lite.js, data-full.js) across all tracked HTML files
       and the two generator scripts must be one identical value.
       A bumped file with an un-bumped reference = stale-immutable-
       cache serving old bytes to real visitors indefinitely.

   (b) Product-data drift (partial-replacement blindspot class,
       DEC-186/188): shop.html cards, index.html bestseller cards,
       ROUTINE_MAP ids and the committed ingredient glossary are
       hand/generator-materialized views of js/data-lite.js — if
       the data moves and a view doesn't, this fails naming the
       exact product/card/entry.

   (c) Generated-output drift (manual-regeneration class): re-runs
       both generators into a temp dir and byte-compares against
       the committed product/*.html + ingredients.html. Editing
       data or a generator without re-running it fails here.

   (d) Single-origin (half-swapped go-live class): every canonical/
       og:url/og:image/twitter:image/JSON-LD origin in tracked HTML,
       sitemap.xml <loc> and llms.txt URL must be ONE origin.

   Verify-don't-restructure: this script only CHECKS; it never
   rewrites shop.html/index.html or converts them to generated
   authoring. Run: node scripts/check-consistency.js
   ============================================================ */

'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');

const failures = [];
function fail(section, msg) {
  failures.push(`[${section}] ${msg}`);
}
function ok(section, msg) {
  console.log(`  OK  [${section}] ${msg}`);
}

/* ---------- shared helpers ---------- */

function loadJsData(relPath, exportNames) {
  const code = fs.readFileSync(path.join(ROOT, relPath), 'utf8');
  const sandbox = { window: {}, module: { exports: {} }, exports: {}, console };
  vm.createContext(sandbox);
  new vm.Script(code + '\n;module.exports = {' + exportNames.join(',') + '};', {
    filename: relPath,
  }).runInContext(sandbox);
  return sandbox.module.exports;
}

function gitTracked(patterns) {
  const out = execFileSync('git', ['ls-files', '--'].concat(patterns), {
    cwd: ROOT,
    encoding: 'utf8',
  });
  return out.split('\n').map(s => s.trim()).filter(Boolean);
}

function read(rel) {
  return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

function decodeEntities(s) {
  return String(s)
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&middot;/g, '·')
    .replace(/&trade;/g, '™');
}

function stripBrand(name) {
  return String(name).replace(/^Kotiva\s+/i, '').trim();
}

/* ============================================================
   (a) Version-token consistency — the DEC-190 class
   ============================================================ */

const VERSIONED_ASSETS = [
  'kotiva.css',
  'layout.js',
  'animations.js',
  'data-lite.js',
  'data-full.js',
  // Icons are cache-busted too, and are just as exposed to the DEC-190 immutable-cache
  // trap as the CSS/JS above: a swapped icon that keeps its old ?v= never reaches a
  // returning visitor. Added 2026-07-20 with the client's brand-icon replacement.
  'favicon.svg',
  'favicon-48.png',
  'apple-touch-icon.png',
];

function checkVersionTokens() {
  const htmlFiles = gitTracked(['*.html', '**/*.html']);
  const scanFiles = htmlFiles.concat([
    'scripts/generate-product-pages.js',
    'scripts/generate-ingredients-page.js',
  ]);

  for (const asset of VERSIONED_ASSETS) {
    const re = new RegExp(asset.replace('.', '\\.') + '\\?v=([A-Za-z0-9._-]+)', 'g');
    const tokens = new Map(); // token -> [file, ...]
    for (const rel of scanFiles) {
      const text = read(rel);
      let m;
      while ((m = re.exec(text)) !== null) {
        if (!tokens.has(m[1])) tokens.set(m[1], []);
        tokens.get(m[1]).push(rel);
      }
    }
    if (tokens.size === 0) {
      fail('version-tokens', `${asset}: no ?v= references found at all (scan broken or asset unreferenced)`);
    } else if (tokens.size > 1) {
      const detail = [...tokens.entries()]
        .map(([tok, files]) => `?v=${tok} in ${files.length} file(s), e.g. ${files.slice(0, 3).join(', ')}`)
        .join(' | ');
      fail('version-tokens', `${asset}: INCONSISTENT cache-bust tokens (DEC-190 class): ${detail}`);
    } else {
      const [tok, files] = [...tokens.entries()][0];
      ok('version-tokens', `${asset}?v=${tok} consistent across ${files.length} reference(s)`);
    }
  }
}

/* ============================================================
   (b) Product-data drift — data-lite.js vs its materialized views
   ============================================================ */

function checkProductDataDrift() {
  const { KOTIVA_PRODUCTS, ROUTINE_MAP } = loadJsData('js/data-lite.js', [
    'KOTIVA_PRODUCTS',
    'ROUTINE_MAP',
  ]);
  const byId = new Map(KOTIVA_PRODUCTS.map(p => [p.id, p]));
  const bySlug = new Map(KOTIVA_PRODUCTS.map(p => [p.slug, p]));

  /* --- shop.html product cards --- */
  const shop = read('shop.html');

  // Valid concern-filter tokens = the pills shop.html itself declares.
  // (Card data-concern is SPACE-SEPARATED multi-token by design — the inline
  // shop filter script does .split(' ') — so validate each token, not the raw value.)
  const pillTokens = new Map(); // token -> declared count from the pill label, or null
  for (const m of shop.matchAll(/<button class="filter-pill"[^>]*data-concern="([^"]*)"[^>]*>([^<]*)<\/button>/g)) {
    if (!m[1]) continue;
    const countM = m[2].match(/\((\d+)\)/);
    pillTokens.set(m[1], countM ? Number(countM[1]) : null);
  }

  // Two shop-card formats exist. The release/pc-* line (client-approved base) uses
  // slug-href + data-tags cards; the facets feature branch uses data-id + data-concern.
  // Detect the format and validate accordingly — the gate must be true for the tree it
  // runs on, not for a feature the tree intentionally excludes (rule 15 / DEC-334).
  const rawCards = [...shop.matchAll(/<a class="product-card"([^>]*)>([\s\S]*?)<\/a>/g)];
  const isIdFormat = rawCards.some((m) => /data-id="\d+"/.test(m[1]));

  if (!isIdFormat) {
    /* --- slug-format validation (approved-base shop.html) --- */
    const bySlug = new Map(KOTIVA_PRODUCTS.map((p) => [p.slug, p]));
    const seenSlugs = new Map(); // slug -> card name
    for (const m of rawCards) {
      const attrs = m[1];
      const body = m[2];
      const hrefM = attrs.match(/href="\/product\/([a-z0-9-]+)\.html"/);
      if (!hrefM) {
        fail('shop-cards', `product-card with unparseable href: ${attrs.trim().slice(0, 120)}`);
        continue;
      }
      const slug = hrefM[1];
      if (seenSlugs.has(slug)) fail('shop-cards', `duplicate shop card for slug=${slug}`);
      const nameM = body.match(/<div class="product-card-name">([\s\S]*?)<\/div>/);
      seenSlugs.set(slug, nameM ? decodeEntities(nameM[1].trim()) : null);
      if (!bySlug.has(slug)) {
        fail('shop-cards', `shop.html has a card for slug=${slug} but no such product exists in data`);
      }
    }
    for (const p of KOTIVA_PRODUCTS) {
      if (!seenSlugs.has(p.slug)) {
        fail('shop-cards', `product id=${p.id} "${p.name}" has NO card on shop.html`);
      } else if (seenSlugs.get(p.slug) !== stripBrand(p.name)) {
        fail('shop-cards', `slug=${p.slug}: card name "${seenSlugs.get(p.slug)}" != data name "${stripBrand(p.name)}"`);
      }
    }
    if (!failures.some((f) => f.startsWith('[shop-cards]'))) {
      ok('shop-cards', `${seenSlugs.size} shop cards match ${KOTIVA_PRODUCTS.length} products (slug link + name; slug-format)`);
    }
  }

  const cards = new Map(); // data-id (number) -> {href, concern, hasConcern, name, raw}
  for (const m of (isIdFormat ? rawCards : [])) {
    const attrs = m[1];
    const body = m[2];
    const idM = attrs.match(/data-id="(\d+)"/);
    if (!idM) {
      fail('shop-cards', `product-card without data-id: ${attrs.trim().slice(0, 120)}`);
      continue;
    }
    const id = Number(idM[1]);
    if (cards.has(id)) fail('shop-cards', `duplicate shop card for data-id=${id}`);
    const hrefM = attrs.match(/href="([^"]*)"/);
    const concernM = attrs.match(/data-concern="([^"]*)"/);
    const nameM = body.match(/<div class="product-card-name">([\s\S]*?)<\/div>/);
    cards.set(id, {
      href: hrefM ? hrefM[1] : null,
      hasConcern: !!concernM,
      concern: concernM ? concernM[1] : null,
      name: nameM ? decodeEntities(nameM[1].trim()) : null,
    });
  }

  for (const p of (isIdFormat ? KOTIVA_PRODUCTS : [])) {
    const card = cards.get(p.id);
    if (!card) {
      fail('shop-cards', `product id=${p.id} "${p.name}" has NO card on shop.html`);
      continue;
    }
    const wantHref = `/product/${p.slug}.html`;
    if (card.href !== wantHref) {
      fail('shop-cards', `id=${p.id} "${p.name}": card href "${card.href}" != "${wantHref}"`);
    }
    if (card.name !== stripBrand(p.name)) {
      fail('shop-cards', `id=${p.id}: card name "${card.name}" != data name "${stripBrand(p.name)}"`);
    }
    if (!card.hasConcern) {
      fail('shop-cards', `id=${p.id} "${p.name}": card is missing the data-concern attribute`);
    } else {
      for (const tok of card.concern.split(' ').filter(Boolean)) {
        if (!pillTokens.has(tok)) {
          fail('shop-cards', `id=${p.id} "${p.name}": data-concern token "${tok}" matches NO filter pill on shop.html (dead/typo token)`);
        }
      }
    }
  }
  // Pill count labels ("Brightening (7)") vs actual matching cards.
  for (const [tok, declared] of pillTokens) {
    if (declared == null) continue;
    let actual = 0;
    for (const c of cards.values()) {
      if ((c.concern || '').split(' ').filter(Boolean).includes(tok)) actual++;
    }
    if (actual !== declared) {
      fail('shop-cards', `filter pill "${tok}" declares (${declared}) but ${actual} card(s) carry that concern token`);
    }
  }
  for (const id of cards.keys()) {
    if (!byId.has(id)) fail('shop-cards', `shop.html has a card for data-id=${id} but no such product exists in data-lite.js`);
  }
  if (isIdFormat && !failures.some(f => f.startsWith('[shop-cards]'))) {
    ok('shop-cards', `${cards.size} shop cards match ${KOTIVA_PRODUCTS.length} products (id, slug link, name, concern token)`);
  }

  /* --- index.html bestseller cards --- */
  const index = read('index.html');
  let bsCount = 0;
  let bsBad = false;
  for (const m of index.matchAll(/<a class="bs-card" href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/g)) {
    bsCount++;
    const href = m[1];
    const nameM = m[2].match(/<div class="bs-card-name">([\s\S]*?)<\/div>/);
    const name = nameM ? decodeEntities(nameM[1].trim()) : null;
    const slugM = href.match(/^\/product\/([a-z0-9-]+)\.html$/);
    if (!slugM) {
      fail('index-bestsellers', `bs-card href "${href}" is not a /product/<slug>.html link`);
      bsBad = true;
      continue;
    }
    const p = bySlug.get(slugM[1]);
    if (!p) {
      fail('index-bestsellers', `bs-card links to unknown product slug "${slugM[1]}"`);
      bsBad = true;
      continue;
    }
    if (p.bestSeller !== true) {
      fail('index-bestsellers', `bs-card "${name}" (id=${p.id}) links to a product whose bestSeller flag is ${JSON.stringify(p.bestSeller)} in data-lite.js`);
      bsBad = true;
    }
    if (name !== stripBrand(p.name)) {
      fail('index-bestsellers', `bs-card name "${name}" != data name "${stripBrand(p.name)}" (id=${p.id})`);
      bsBad = true;
    }
  }
  if (bsCount === 0) {
    fail('index-bestsellers', 'no bs-card elements found on index.html (markup changed? update this check)');
  } else if (!bsBad) {
    ok('index-bestsellers', `${bsCount} bestseller cards on index.html all match bestSeller:true products (name + link)`);
  }

  /* --- the rail's terminus panel: a hardcoded count that must equal a derived one ---
     The panel closing the product rail says "N more products in the full range". N is
     total products minus the ones the rail already showed, so it silently goes wrong the
     moment a bestSeller flag is added or removed -- exactly the desync class that put six
     hardcoded progress dots against eight rail items. A number in markup that restates a
     fact the data already knows gets checked against the data, or it is a liability. */
  const endCountM = index.match(/<span class="bs-end-count">(\d+)<\/span>/);
  if (!endCountM) {
    fail('index-rail-terminus', 'no .bs-end-count element found on index.html (markup changed? update this check)');
  } else {
    const stated = Number(endCountM[1]);
    const expected = KOTIVA_PRODUCTS.length - bsCount;
    if (stated !== expected) {
      fail('index-rail-terminus', `rail terminus says "${stated} more products" but ${KOTIVA_PRODUCTS.length} products - ${bsCount} rail cards = ${expected}`);
    } else {
      ok('index-rail-terminus', `rail terminus count ${stated} = ${KOTIVA_PRODUCTS.length} products - ${bsCount} shown in the rail`);
    }
    const ariaM = index.match(/<a class="bs-end"[^>]*aria-label="([^"]*)"/);
    if (ariaM && !ariaM[1].includes(String(expected))) {
      fail('index-rail-terminus', `rail terminus aria-label "${ariaM[1]}" does not carry the correct count ${expected}`);
    }
  }

  /* --- ROUTINE_MAP referential integrity --- */
  let routineBad = false;
  let routineRefs = 0;
  for (const [skin, slots] of Object.entries(ROUTINE_MAP)) {
    for (const [slot, ids] of Object.entries(slots)) {
      for (const id of ids) {
        routineRefs++;
        if (!byId.has(id)) {
          fail('routine-map', `ROUTINE_MAP.${skin}.${slot} references product id=${id} which does not exist in KOTIVA_PRODUCTS`);
          routineBad = true;
        }
      }
    }
  }
  if (!routineBad) ok('routine-map', `${routineRefs} routine slot references all resolve to real product ids`);

  /* --- ingredient glossary: the silent-drop class --- */
  const { KOTIVA_INGREDIENTS } = loadJsData('js/ingredients-glossary.js', ['KOTIVA_INGREDIENTS']);
  // Same matching as scripts/generate-ingredients-page.js productsFor():
  const productsFor = active => {
    const aliasSet = new Set(active.aliases);
    return KOTIVA_PRODUCTS.filter(p => (p.ingredients || []).some(i => aliasSet.has(i)));
  };
  const activeBySlug = new Map(KOTIVA_INGREDIENTS.map(a => [a.slug, a]));
  const committedSlugs = [...read('ingredients.html').matchAll(/<article class="ig-card" id="([^"]+)"/g)].map(m => m[1]);
  let glossaryBad = false;
  for (const slug of committedSlugs) {
    const active = activeBySlug.get(slug);
    if (!active) {
      fail('glossary', `committed ingredients.html has entry "#${slug}" that no longer exists in js/ingredients-glossary.js`);
      glossaryBad = true;
      continue;
    }
    const n = productsFor(active).length;
    if (n === 0) {
      fail('glossary', `glossary entry "${active.name}" (#${slug}) HAD products but its aliases now match 0 products in data-lite.js — it would be silently DROPPED on regeneration (silent-drop class)`);
      glossaryBad = true;
    }
  }
  // Entries the data says should exist but the committed page lacks (stale page).
  const expectedSlugs = KOTIVA_INGREDIENTS.filter(a => productsFor(a).length > 0).map(a => a.slug);
  for (const slug of expectedSlugs) {
    if (!committedSlugs.includes(slug)) {
      fail('glossary', `active "#${slug}" matches products in data-lite.js but is MISSING from committed ingredients.html (page stale — regenerate)`);
      glossaryBad = true;
    }
  }
  if (!glossaryBad) {
    ok('glossary', `${committedSlugs.length} committed glossary entries all still resolve to >=1 product; no expected entry missing`);
  }
}

/* ============================================================
   (c) Generated-output drift — regenerate into a temp dir, byte-compare
   ============================================================ */

function checkGeneratedOutputDrift() {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'kotiva-consistency-'));
  try {
    // The generators only read js/* and write product/* + ingredients.html
    // relative to their repo root, so a minimal repo copy is sufficient.
    fs.cpSync(path.join(ROOT, 'js'), path.join(tmp, 'js'), { recursive: true });
    fs.cpSync(path.join(ROOT, 'scripts'), path.join(tmp, 'scripts'), { recursive: true });
    // data/ is a generator INPUT since G2 (routine-model supplies each product's
    // format and zone for the facts strip). Without it the drift re-run cannot
    // execute at all — so any new generator input must be copied here too.
    fs.cpSync(path.join(ROOT, 'data'), path.join(tmp, 'data'), { recursive: true });

    for (const gen of ['generate-product-pages.js', 'generate-ingredients-page.js']) {
      execFileSync(process.execPath, [path.join(tmp, 'scripts', gen)], { stdio: 'pipe' });
    }

    const diffs = [];
    const compare = (rel, tmpRel) => {
      const committedPath = path.join(ROOT, rel);
      const freshPath = path.join(tmp, tmpRel || rel);
      if (!fs.existsSync(freshPath)) {
        diffs.push(`${rel}: committed but generator no longer produces it`);
        return;
      }
      const a = fs.readFileSync(committedPath);
      const b = fs.readFileSync(freshPath);
      if (!a.equals(b)) diffs.push(`${rel}: committed bytes != freshly generated bytes`);
    };

    const committedProducts = gitTracked(['product/*.html']);
    for (const rel of committedProducts) compare(rel);
    compare('ingredients.html');

    // Generator now emits pages that are not committed at all?
    const fresh = fs.readdirSync(path.join(tmp, 'product')).filter(f => f.endsWith('.html'));
    for (const f of fresh) {
      if (!committedProducts.includes(`product/${f}`)) {
        diffs.push(`product/${f}: generator produces it but it is not committed`);
      }
    }

    if (diffs.length) {
      for (const d of diffs) {
        fail('generated-drift', `${d} — re-run the generators and commit (manual-regeneration class)`);
      }
    } else {
      ok('generated-drift', `${committedProducts.length} product pages + ingredients.html byte-identical to a fresh generator run`);
    }
  } finally {
    fs.rmSync(tmp, { recursive: true, force: true });
  }
}

/* ============================================================
   (d) Single-origin — catches a half-swapped go-live
   All canonical/og:url/og:image/twitter:image/JSON-LD origins in
   tracked HTML, every sitemap.xml <loc>, and every absolute URL
   in llms.txt must resolve to ONE single origin. (schema.org
   vocabulary URLs inside JSON-LD are excluded — they are type
   identifiers, not site URLs.)
   ============================================================ */

function checkSingleOrigin() {
  const originOf = url => {
    const m = String(url).match(/^(https?:\/\/[^/"'\s]+)/);
    return m ? m[1].toLowerCase() : null;
  };
  const IGNORED_HOSTS = /(^|\.)schema\.org$/;
  const seen = new Map(); // origin -> Set(files)
  const record = (url, file) => {
    const origin = originOf(url);
    if (!origin) return; // relative URL — fine
    const host = origin.replace(/^https?:\/\//, '');
    if (IGNORED_HOSTS.test(host)) return;
    if (!seen.has(origin)) seen.set(origin, new Set());
    seen.get(origin).add(file);
  };

  for (const rel of gitTracked(['*.html', '**/*.html'])) {
    const html = read(rel);
    for (const m of html.matchAll(/<link rel="canonical" href="([^"]+)"/g)) record(m[1], rel);
    for (const m of html.matchAll(/<meta (?:property|name)="(?:og:url|og:image|twitter:image)" content="([^"]+)"/g)) record(m[1], rel);
    for (const m of html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
      for (const u of m[1].matchAll(/https?:\/\/[^"\\\s]+/g)) record(u[0], `${rel} (JSON-LD)`);
    }
  }
  for (const m of read('sitemap.xml').matchAll(/<loc>([^<]+)<\/loc>/g)) record(m[1], 'sitemap.xml');
  for (const m of read('llms.txt').matchAll(/https?:\/\/[^\s)]+/g)) record(m[0], 'llms.txt');

  if (seen.size > 1) {
    const detail = [...seen.entries()]
      .map(([origin, files]) => `${origin} <- ${[...files].slice(0, 4).join(', ')}${files.size > 4 ? ` (+${files.size - 4} more)` : ''}`)
      .join('\n         ');
    fail('single-origin', `MULTIPLE site origins found (half-swapped go-live class):\n         ${detail}`);
  } else if (seen.size === 1) {
    const [origin, files] = [...seen.entries()][0];
    ok('single-origin', `all canonical/og/JSON-LD/sitemap/llms.txt URLs use ${origin} (${files.size} file(s))`);
  } else {
    fail('single-origin', 'no absolute site URLs found at all (scan broken?)');
  }
}

/* ============================================================
   (e) Quiz-question-count drift — partial-replacement blindspot,
       the same class as (b) but for a FACT stated in prose rather
       than a data-derived view.

   On 2026-08-11 the Routine Finder was rebuilt from 3 questions to
   7. The FAQ's stale count was found and corrected; the same fact
   hardcoded in two promotional sentences was not, so the site
   promised "3 quick questions" on the hero of the very page that
   then asked 7 — and contradicted its own FAQ, which correctly
   said "seven". Caught by the manual gate, one dispatch before the
   client would have seen it.

   The count is authored in prose in several places and derived
   from the DOM in exactly one. This makes the DOM authoritative
   and every sentence check itself against it.
   ============================================================ */

const NUMBER_WORDS = {
  one: 1, two: 2, three: 3, four: 4, five: 5, six: 6,
  seven: 7, eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12,
};

function checkQuizQuestionCount() {
  const quiz = read('routine-finder.html');
  const actual = (quiz.match(/id="step-\d+"/g) || []).length;

  if (!actual) {
    fail('quiz-count', 'no quiz steps (id="step-N") found in routine-finder.html — scan broken?');
    return;
  }

  const claimRe = /\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)((?:\s+[a-z]+){0,2}\s+questions?)\b/gi;
  const claims = [];
  for (const rel of gitTracked(['*.html', '**/*.html'])) {
    for (const m of read(rel).matchAll(claimRe)) {
      const raw = m[1].toLowerCase();
      const n = /^\d+$/.test(raw) ? Number(raw) : NUMBER_WORDS[raw];
      if (n === undefined) continue;
      claims.push({ rel, n, text: (m[1] + m[2]).replace(/\s+/g, ' ') });
    }
  }

  if (!claims.length) {
    ok('quiz-count', `${actual} quiz steps; no prose claims to verify`);
    return;
  }

  const wrong = claims.filter(c => c.n !== actual);
  if (wrong.length) {
    const detail = wrong
      .map(c => `${c.rel}: "${c.text}" — the quiz has ${actual}`)
      .join('\n         ');
    fail('quiz-count',
      `question-count claim(s) disagree with the quiz itself:\n         ${detail}`);
  } else {
    ok('quiz-count',
      `${actual} quiz steps; all ${claims.length} prose claim(s) agree ` +
      `(${[...new Set(claims.map(c => c.rel))].join(', ')})`);
  }
}

/* ============================================================
   run
   ============================================================ */

console.log('KOTIVA consistency gate — scripts/check-consistency.js');
checkVersionTokens();
checkProductDataDrift();
checkGeneratedOutputDrift();
checkSingleOrigin();
checkQuizQuestionCount();

if (failures.length) {
  console.error(`\nFAIL — ${failures.length} consistency violation(s):`);
  for (const f of failures) console.error(`  FAIL ${f}`);
  process.exit(1);
}
console.log('\nPASS — all consistency checks green.');
