/* ============================================================
   KOTIVA™ — Routine Finder "Shop These Products" href
   Run: node resources/routine/test-routine-cta.mjs (part of npm run test:routine)

   Exists because the CTA used to resolve routine-model ids against
   /api/products rows keyed by DATABASE id. Those only matched by
   accident of auto-increment, so after a reseed the handoff silently
   lost ?routine=. The SKU is now derived from the model id (D-39).
   ============================================================ */
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readFileSync } from 'node:fs';

const __dir = dirname(fileURLToPath(import.meta.url));
const root = join(__dir, '..', '..');
const require = createRequire(import.meta.url);

const cta = require(join(root, 'public/js/routine-cta.js'));
const engine = require(join(root, 'public/js/routine-engine.js'));
const model = JSON.parse(readFileSync(join(root, 'resources/routine/routine-model.json'), 'utf8'));

const STORE = 'https://store.example.test';
let failures = 0, checks = 0;
const eq = (name, got, want) => {
  if (got === want) { checks++; console.log(`  ✓ ${name}`); }
  else { failures++; console.error(`  ✗ ${name}: got ${JSON.stringify(got)}, want ${JSON.stringify(want)}`); }
};
const step = id => ({ id });

console.log('\nKOTIVA routine CTA\n');

eq('ids 7, 11, 13, 25 become KOT SKUs on the listing URL',
  cta.ctaHref(STORE, { am: [step(7)], pm: [step(11), step(13)], anytime: [step(25)] }),
  `${STORE}/?routine=KOT007,KOT011,KOT013,KOT025`);

eq('duplicates are dropped and am → pm → anytime order is kept',
  cta.ctaHref(STORE, { am: [step(13), step(7)], pm: [step(7), step(25)], anytime: [step(13), step(11)] }),
  `${STORE}/?routine=KOT013,KOT007,KOT025,KOT011`);

eq('non-integer and out-of-range ids are skipped',
  cta.ctaHref(STORE, { am: [step(7), step(1.5), step('x'), step(null)], pm: [step(0), step(1000), step(-3)], anytime: [{}, step(25)] }),
  `${STORE}/?routine=KOT007,KOT025`);

eq('no SKUs falls back to the bare store root (Salla::listingUrl(null, []))',
  cta.ctaHref(STORE, { am: [], pm: [], anytime: [step('nope')] }), STORE);

eq('skuOf pads to three digits', [1, 9, 10, 99, 100, 999].map(cta.skuOf).join(','),
  'KOT001,KOT009,KOT010,KOT099,KOT100,KOT999');

eq('listingUrl upper-cases, dedupes and drops junk like the PHP helper',
  cta.listingUrl(STORE, ['kot001', 'KOT001', '', 'KOT 2', '<script>', 'KOT005']),
  `${STORE}/?routine=KOT001,KOT005`);

/* every real build() result yields one SKU per distinct recommended id */
{
  let bad = null;
  for (const zones of [['face'], ['body'], ['hair'], ['face', 'body', 'hair']])
    for (const skinType of ['oily', 'dry', 'combination', 'balanced'])
      for (const routineSize of ['simple', 'essentials', 'full']) {
        const r = engine.build({ zones, skinType, sensitive: false, concerns: [model.concerns.options[0].id], routineSize, experience: 'advanced', texture: null }, model);
        const ids = [...new Set([...r.am, ...r.pm, ...r.anytime].map(s => s.id))];
        const want = ids.length ? `${STORE}/?routine=${ids.map(i => 'KOT' + String(i).padStart(3, '0')).join(',')}` : STORE;
        if (cta.ctaHref(STORE, r) !== want) { bad = { zones, skinType, routineSize }; break; }
      }
  eq('real engine results map one-to-one onto model-derived SKUs', bad, null);
}

console.log(`\n${failures ? '✗ FAILED' : '✓ PASSED'} — ${checks} checks, ${failures} failures\n`);
process.exit(failures ? 1 : 0);
