#!/usr/bin/env node
/* ============================================================
   Generates js/routine-model.js from data/routine-model.json.

   data/routine-model.json is the single authored source. It carries the
   full provenance apparatus (evidence quotes, ratification flags) that the
   client ratification sheet is built from. The browser only needs the
   fields the engine actually reads, so the prose is stripped here.

   NEVER hand-edit js/routine-model.js — run this instead:
       node scripts/generate-routine-model.js
   scripts/check-consistency.js fails the build if the two drift.
   ============================================================ */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const srcPath = path.join(root, 'resources/routine/routine-model.json');
const outPath = path.join(root, 'public/js/routine-model.js');

/* Fields the runtime engine + result UI genuinely need. Everything else
   (evidence quotes, provenance tags, ratification notes) stays in the JSON. */
const KEEP = [
  'name', 'step', 'zone', 'slot', 'frequency', 'frequencyReduced', 'format',
  'skinTypes', 'sensitivitySafe', 'concerns', 'acids', 'retinoid',
  'unprompted', 'routineExcluded', 'positioning', 'cautions', 'slotProv'
];

function build() {
  const src = JSON.parse(fs.readFileSync(srcPath, 'utf8'));
  const products = {};
  for (const [id, p] of Object.entries(src.products)) {
    const out = {};
    for (const k of KEEP) if (p[k] !== undefined) out[k] = p[k];
    products[id] = out;
  }
  const model = {
    stepOrder: src.stepOrder.value,
    concerns: src.concerns,
    skinTypes: src.skinTypes,
    products
  };
  const banner =
    '/* GENERATED FILE — do not edit.\n' +
    '   Source: data/routine-model.json\n' +
    '   Regenerate: node scripts/generate-routine-model.js\n' +
    '   The authored source carries the full provenance record; this file\n' +
    '   carries only what the runtime needs. */\n';
  const body = 'window.KOTIVA_ROUTINE_MODEL = ' + JSON.stringify(model, null, 1) + ';\n';
  return banner + body;
}

const next = build();

if (process.argv.includes('--check')) {
  const current = fs.existsSync(outPath) ? fs.readFileSync(outPath, 'utf8') : '';
  if (current !== next) {
    console.error('✗ routine-model drift: js/routine-model.js does not match data/routine-model.json');
    console.error('  run: node scripts/generate-routine-model.js');
    process.exit(1);
  }
  console.log('✓ routine-model in sync');
  process.exit(0);
}

fs.writeFileSync(outPath, next, 'utf8');
console.log(`✓ wrote public/js/routine-model.js (${Object.keys(JSON.parse(fs.readFileSync(srcPath, 'utf8')).products).length} products)`);
