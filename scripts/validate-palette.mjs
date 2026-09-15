#!/usr/bin/env node
/* ============================================================
   KOTIVA palette validator — the AUTHORITATIVE colour gate (brief §7).

   Parses every colour literal in css/, js/, scripts/, assets/ (SVG + text), root *.html and
   product/*.html — generated output AND generator templates, comments and var() fallbacks
   included — normalises it to RGB + alpha, and fails unless:
     1. the base RGB is one of the ten approved KOTIVA colours (or 0,0,0 in a retained shadow), and
     2. the alpha is an approved fixed combination (§2.2 table) or belongs to a controlled family
        validated against the AUDITED PRE-MIGRATION baseline in
        docs/colour-migration/alpha-baseline.json (shadows, editorial overlays, status, and the
        documented existing overlay/shadow exceptions).
   Named colours (white, red, …) always fail. It validates SOURCE literals only — never
   browser-composited effective colours. Rules live in scripts/lib/palette-rules.mjs, shared
   with the computed-style audit.

   Also asserts every --script declaration in css/kotiva.css is #D7282F.
   Run: node scripts/validate-palette.mjs
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { extractColours, normalised } from './lib/colour-literals.mjs';
import { scanFiles } from './lib/scan-files.mjs';
import { loadPaletteRules } from './lib/palette-rules.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const verdict = loadPaletteRules(ROOT);

const failures = [];
let total = 0;
const seen = new Map();
for (const rel of scanFiles(ROOT)) {
  const text = fs.readFileSync(path.join(ROOT, rel), 'utf8');
  for (const hit of extractColours(text, path.extname(rel).toLowerCase())) {
    total++;
    const v = verdict(hit);
    const norm = hit.colour ? normalised(hit.colour) : hit.raw;
    seen.set(norm, (seen.get(norm) || 0) + 1);
    if (v) failures.push(`${rel}:${hit.line}  ${hit.raw}${hit.comment ? ' (in comment)' : ''}  — ${v}`);
  }
}

// Every --script declaration must be KOTIVA red.
const css = fs.readFileSync(path.join(ROOT, 'css/kotiva.css'), 'utf8');
const scripts = [...css.matchAll(/--script\s*:\s*([^;]+);/g)].map((m) => m[1].trim());
if (!scripts.length) failures.push('css/kotiva.css: no --script declarations found (scan broken?)');
for (const v of scripts) if (v.toUpperCase() !== '#D7282F') failures.push(`css/kotiva.css: --script declared as ${v}, must be #D7282F`);

console.log('KOTIVA palette validator — scripts/validate-palette.mjs');
console.log(`  scanned ${total} colour literals; ${seen.size} distinct normalised values; ${scripts.length} --script declarations`);
if (failures.length) {
  console.error(`\nFAIL — ${failures.length} palette violation(s):`);
  for (const f of failures) console.error(`  FAIL ${f}`);
  process.exit(1);
}
console.log('\nPASS — every colour literal is an approved KOTIVA base colour with an approved alpha.');
