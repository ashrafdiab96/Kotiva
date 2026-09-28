#!/usr/bin/env node
/* ============================================================
   Colour inventory for the brand-colour migration (brief §3).

   Scans css/, js/, scripts/, assets/ (SVG + text), root *.html and product/*.html, and lists
   every colour literal with file, line, selector/element context, property, whether it sits in
   a comment / gradient / shadow, its family (docs/colour-migration/old-palette-families.json)
   and, for generated pages, whether the literal comes from the generator template.

   Usage: node scripts/colour-inventory.mjs --label before|after
   Writes docs/colour-migration/inventory-<label>.json and prints family counts.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { extractColours, keyOf, normalised } from './lib/colour-literals.mjs';
import { scanFiles } from './lib/scan-files.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, def) => {
  const i = process.argv.indexOf(`--${name}`);
  return i === -1 ? def : process.argv[i + 1];
};
const label = arg('label', 'current');

const families = JSON.parse(fs.readFileSync(path.join(ROOT, 'docs/colour-migration/old-palette-families.json'), 'utf8'));
const familyOf = new Map();
for (const [fam, keys] of Object.entries(families.families)) for (const k of keys) familyOf.set(k, fam);
const approved = new Set(families.approved);

const generatorFor = (rel) => {
  if (rel.startsWith('product/')) return 'scripts/generate-product-pages.js';
  if (rel === 'ingredients.html') return 'scripts/generate-ingredients-page.js';
  return null;
};
const genText = {};

const rows = [];
for (const rel of scanFiles(ROOT)) {
  const text = fs.readFileSync(path.join(ROOT, rel), 'utf8');
  const ext = path.extname(rel).toLowerCase();
  for (const hit of extractColours(text, ext)) {
    const gen = generatorFor(rel);
    if (gen && !genText[gen]) genText[gen] = fs.readFileSync(path.join(ROOT, gen), 'utf8');
    const key = hit.colour ? keyOf(hit.colour) : null;
    rows.push({
      file: rel,
      line: hit.line,
      raw: hit.raw,
      kind: hit.kind,
      value: hit.colour ? normalised(hit.colour) : null,
      rgb: key,
      alpha: hit.colour ? hit.colour.a : null,
      family: key ? (approved.has(key) ? (familyOf.get(key) === 'black-shadow' ? 'black-shadow' : 'approved') : familyOf.get(key) || 'unlisted') : hit.kind,
      selector: hit.selector,
      property: hit.property,
      comment: hit.comment,
      gradient: hit.gradient,
      shadow: hit.shadow,
      generatorOwned: gen ? genText[gen].includes(hit.raw) : null,
    });
  }
}

const outPath = path.join(ROOT, `docs/colour-migration/inventory-${label}.json`);
fs.writeFileSync(outPath, JSON.stringify(rows, null, 1) + '\n');

const byFamily = {};
for (const r of rows) {
  const f = (byFamily[r.family] ||= { total: 0, code: 0, comment: 0, generated: 0 });
  f.total++;
  if (r.comment) f.comment++;
  else f.code++;
  if (r.file.startsWith('product/') || r.file === 'ingredients.html') f.generated++;
}
console.log(`colour inventory (${label}): ${rows.length} literal occurrences -> ${path.relative(ROOT, outPath)}`);
console.table(byFamily);
