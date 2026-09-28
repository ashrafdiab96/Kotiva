/**
 * Brochure price extractor — docs/pricing/*.pdf -> brochure-prices.json
 *
 * The A2 brochure is ONE page laid out as six vertical columns of product
 * blocks. `pdftotext -layout` flattens it into a wide fixed-column text grid,
 * where a product's heading and its "PRICE: n SAR" line share a column band
 * but sit dozens of lines apart, with other products' text interleaved.
 *
 * Reading the prices in text order therefore assigns them to the wrong
 * products. Instead every token keeps its (line, column) and a price is bound
 * to the NEAREST PRECEDING HEADING IN ITS OWN COLUMN BAND — the brochure's
 * actual visual grouping.
 *
 * Run: node docs/pricing/extract-brochure-prices.mjs   (needs pdftotext on PATH)
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));

/** Left edge of each product column, as pdftotext -layout reports it. */
const BAND_EDGES = [0, 80, 205, 305, 410, 510];

const VAT_RATE = 0.15;

/**
 * Brochure heading -> catalog SKU.
 *
 * Matched on the heading text the brochure actually prints, which differs from
 * the catalog name in casing and wording for several products. Kept explicit
 * rather than fuzzy-matched: a near-miss on a price mapping is a silent
 * mispricing, which is exactly what this script exists to rule out.
 */
const HEADINGS = [
  ['Kotiva Micellar Water with', 'KOT001'],
  ['kotiva acne cleansing gel', 'KOT002'],
  ['kotiva Hyaluronic Facial Cleanser', 'KOT003'],
  ['kotiva Facial Foam for Sensitive Skin', 'KOT004'],
  ['Kotiva Glow up Water Essence Toner', 'KOT005'],
  ['Kotiva Oil Control Foam', 'KOT006'],
  ['Kotiva Face and Body Cleansing Foam', 'KOT007'],
  ['Kotiva Post Fillers Lip Balm', 'KOT008'],
  ['Kotiva Sun Protection Spf 50+', 'KOT009'],
  ['Kotiva After Sun Cream', 'KOT010'],
  ['Kotiva Younger Hand Cream', 'KOT011'],
  ['Kotiva Whitening Hand Cream', 'KOT012'],
  ['Kotiva Anti-Perspirant Roll On', 'KOT013'],
  ['Kotiva Whitening Anti-Perspirant Roll On', 'KOT014'],
  ['Kotiva Hydroshield Post Laser Cream', 'KOT015'],
  ['Kotiva Triple Action Whitening Day Cream', 'KOT016'],
  ['Kotiva Triple Action Whitening Night Cream', 'KOT017'],
  ['Kotiva Triple Action Bikini Area Whitening Cream', 'KOT018'],
  ['Kotiva Triple Action Whitening Wash Gel for Sensitive Area', 'KOT019'],
  ['Kotiva Acne Control', 'KOT020'],
  ['Kotiva Pores Off Serum', 'KOT021'],
  ['Kotiva UV Balance Spf 50+', 'KOT022'],
  ['Kotiva Anti-Hair Loss Ampoules', 'KOT023'],
  ['Kotiva Anti-Hair Loss Shampoo', 'KOT024'],
  ['Siliscar Gel', 'KOT025'],
];

const bandOf = (col) => {
  let band = 0;
  for (let i = 0; i < BAND_EDGES.length; i++) {
    if (col >= BAND_EDGES[i]) band = i;
  }
  return band;
};

/** Every non-whitespace run on a line, with its starting column. */
function segments(line) {
  const out = [];
  const re = /\S(?:.*?\S)?(?=\s{3,}|$)/g;
  let m;
  while ((m = re.exec(line)) !== null) {
    if (m[0].trim() !== '') out.push({ col: m.index, text: m[0].trim() });
    if (m.index === re.lastIndex) re.lastIndex++;
  }
  return out;
}

function extract(pdfPath) {
  const out = join(mkdtempSync(join(tmpdir(), 'kotiva-')), 'page.txt');
  execFileSync('pdftotext', ['-layout', pdfPath, out]);
  const lines = readFileSync(out, 'utf8').replace(/\r/g, '').split('\n');

  const headings = [];
  const prices = [];

  lines.forEach((raw, i) => {
    for (const { col, text } of segments(raw)) {
      for (const [label, sku] of HEADINGS) {
        // startsWith, not equals: pdftotext merges a neighbouring column's text
        // into the same segment wherever the gap narrows to under three spaces.
        if (text.toLowerCase().startsWith(label.toLowerCase())) {
          headings.push({ line: i + 1, band: bandOf(col), sku });
        }
      }

      // "PRICE: 135 SAR" / "PRICE:150 SAR" / "PRICE: 155.25 SAR"
      const re = /PRICE:\s*([0-9]+(?:\.[0-9]+)?)\s*SAR/gi;
      let m;
      while ((m = re.exec(text)) !== null) {
        prices.push({ line: i + 1, col: col + m.index, band: bandOf(col + m.index), amount: m[1] });
      }
    }
  });

  const assigned = {};
  const unassigned = [];

  for (const price of prices) {
    const candidates = headings
      .filter((h) => h.band === price.band && h.line <= price.line)
      .sort((a, b) => b.line - a.line);

    if (candidates.length === 0) {
      unassigned.push({ ...price, reason: 'no heading above it in this column' });
      continue;
    }

    const sku = candidates[0].sku;

    if (assigned[sku] !== undefined) {
      unassigned.push({ ...price, reason: `${sku} already has a price` });
      continue;
    }

    assigned[sku] = price.amount;
  }

  return { assigned, unassigned, headingCount: headings.length, priceCount: prices.length };
}

const excl = extract(join(HERE, 'A2 BROCHURE WITH PRICES WITHOUT TAXES.pdf'));
const incl = extract(join(HERE, 'A2 BROCHURE WITH PRICES.pdf'));

const rows = [];
const problems = [];

for (const [label, sku] of HEADINGS) {
  const e = excl.assigned[sku] ?? null;
  const i = incl.assigned[sku] ?? null;

  if (e === null) problems.push(`${sku}: no VAT-exclusive price found.`);
  if (i === null) problems.push(`${sku}: no VAT-inclusive price found.`);

  let derived = null;
  let agrees = null;

  if (e !== null && i !== null) {
    derived = (Math.round(Number(e) * (1 + VAT_RATE) * 100) / 100).toFixed(2);
    agrees = derived === Number(i).toFixed(2);

    if (!agrees) {
      problems.push(
        `${sku}: brochures disagree — excl ${e} implies ${derived} at ${VAT_RATE * 100}% VAT, ` +
          `but the inclusive brochure prints ${Number(i).toFixed(2)}.`
      );
    }
  }

  rows.push({
    sku,
    brochure_heading: label,
    price_excl_vat: e === null ? null : Number(e).toFixed(2),
    price_incl_vat: i === null ? null : Number(i).toFixed(2),
    implied_incl_at_15pct: derived,
    brochures_agree: agrees,
  });
}

for (const p of excl.unassigned) problems.push(`Exclusive brochure, unassigned price: ${JSON.stringify(p)}`);
for (const p of incl.unassigned) problems.push(`Inclusive brochure, unassigned price: ${JSON.stringify(p)}`);

const report = {
  generated_by: 'docs/pricing/extract-brochure-prices.mjs',
  vat_rate: VAT_RATE,
  sources: {
    exclusive: 'A2 BROCHURE WITH PRICES WITHOUT TAXES.pdf',
    inclusive: 'A2 BROCHURE WITH PRICES.pdf',
  },
  counts: {
    products: rows.length,
    exclusive_prices_found: Object.keys(excl.assigned).length,
    inclusive_prices_found: Object.keys(incl.assigned).length,
    exclusive_price_tokens: excl.priceCount,
    inclusive_price_tokens: incl.priceCount,
  },
  problems,
  products: rows,
};

writeFileSync(join(HERE, 'brochure-prices.json'), JSON.stringify(report, null, 2) + '\n');

console.log(`products:   ${rows.length}`);
console.log(`excl found: ${Object.keys(excl.assigned).length} of ${excl.priceCount} price tokens`);
console.log(`incl found: ${Object.keys(incl.assigned).length} of ${incl.priceCount} price tokens`);
console.log(`problems:   ${problems.length}`);
for (const p of problems) console.log('  ! ' + p);
console.table(rows.map((r) => ({ sku: r.sku, excl: r.price_excl_vat, incl: r.price_incl_vat, agree: r.brochures_agree })));
