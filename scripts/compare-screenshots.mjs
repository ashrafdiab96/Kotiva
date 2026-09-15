#!/usr/bin/env node
/* ============================================================
   Before/after regression comparison for the brand-colour migration (brief §6.7).

   Structural regression is decided from GEOMETRY, not from pixels: every element's box,
   font, visibility, image source and own text must match between the two captures
   (tolerance 0.5px for sub-pixel layout). The pixel diff is reported only to show that the
   pages did change colour, and a few downscaled side-by-side images are written as evidence.

   Usage: node scripts/compare-screenshots.mjs [--before scratch/colour-migration/before]
                                               [--after scratch/colour-migration/after]
   Writes docs/colour-migration/SCREENSHOT-REGRESSION.md (+ evidence/*.png). Exit 1 on any
   structural difference.
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { PAGE_FAMILIES, CELLS } from './lib/pages.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, def) => {
  const i = process.argv.indexOf(`--${name}`);
  return i === -1 ? def : process.argv[i + 1];
};
const beforeDir = path.resolve(ROOT, arg('before', 'scratch/colour-migration/before'));
const afterDir = path.resolve(ROOT, arg('after', 'scratch/colour-migration/after'));
const docDir = path.join(ROOT, 'docs/colour-migration');
const evidenceDir = path.join(docDir, 'evidence');
fs.mkdirSync(evidenceDir, { recursive: true });

const EVIDENCE = new Set(['home__desktop-light', 'home__desktop-dark', 'shop__m390-light', 'product__desktop-light', 'about__desktop-light', 'science__m390-dark']);
const TOL = 0.5;

function compareGeometry(a, b) {
  const diffs = [];
  if (a.scrollHeight !== b.scrollHeight || a.scrollWidth !== b.scrollWidth) {
    diffs.push(`document size ${a.scrollWidth}x${a.scrollHeight} -> ${b.scrollWidth}x${b.scrollHeight}`);
  }
  if (a.rows.length !== b.rows.length) diffs.push(`element count ${a.rows.length} -> ${b.rows.length}`);
  const n = Math.min(a.rows.length, b.rows.length);
  for (let i = 0; i < n; i++) {
    const x = a.rows[i];
    const y = b.rows[i];
    const what = [];
    if (x.tag !== y.tag || x.cls !== y.cls) what.push(`element ${x.tag}.${x.cls} -> ${y.tag}.${y.cls}`);
    for (const k of ['x', 'y', 'w', 'h']) if (Math.abs(x[k] - y[k]) > TOL) what.push(`${k} ${x[k]} -> ${y[k]}`);
    if (x.font !== y.font) what.push(`font ${x.font} -> ${y.font}`);
    if (x.vis !== y.vis) what.push(`visibility ${x.vis} -> ${y.vis}`);
    // ?v= cache-bust tokens are bumped on purpose for every modified asset (brief §5); the image itself is compared by path.
    const bust = (s) => s.replace(/\?v=[A-Za-z0-9._-]+/, '');
    if (bust(x.src) !== bust(y.src)) what.push(`image ${x.src} -> ${y.src}`);
    if (x.text !== y.text) what.push(`text "${x.text}" -> "${y.text}"`);
    if (what.length) diffs.push(`#${i} <${x.tag} class="${x.cls}"> ${what.join('; ')}`);
  }
  return diffs;
}

const browser = await chromium.launch();
const page = await browser.newPage();
await page.setContent('<html><body></body></html>');

async function pixelStats(beforePng, afterPng, evidenceName) {
  return page.evaluate(async ({ a, b, evidenceName }) => {
    const load = async (b64) => {
      const img = new Image();
      img.src = 'data:image/png;base64,' + b64;
      await img.decode();
      return img;
    };
    const [ia, ib] = await Promise.all([load(a), load(b)]);
    const w = Math.min(ia.width, ib.width);
    const h = Math.min(ia.height, ib.height);
    const read = (img) => {
      const c = document.createElement('canvas');
      c.width = w;
      c.height = h;
      const ctx = c.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(img, 0, 0);
      return ctx.getImageData(0, 0, w, h).data;
    };
    const da = read(ia);
    const db = read(ib);
    let changed = 0;
    for (let i = 0; i < da.length; i += 4) {
      if (Math.abs(da[i] - db[i]) > 8 || Math.abs(da[i + 1] - db[i + 1]) > 8 || Math.abs(da[i + 2] - db[i + 2]) > 8) changed++;
    }
    let evidence = null;
    if (evidenceName) {
      const tw = 360;
      const th = Math.min(2400, Math.round((h * tw) / w));
      const c = document.createElement('canvas');
      c.width = tw * 2 + 12;
      c.height = th;
      const ctx = c.getContext('2d');
      ctx.fillStyle = '#FFFFFF';
      ctx.fillRect(0, 0, c.width, c.height);
      const srcH = Math.round((th * w) / tw);
      ctx.drawImage(ia, 0, 0, w, srcH, 0, 0, tw, th);
      ctx.drawImage(ib, 0, 0, w, srcH, tw + 12, 0, tw, th);
      evidence = c.toDataURL('image/png').split(',')[1];
    }
    return { w, h, sizeA: [ia.width, ia.height], sizeB: [ib.width, ib.height], changedPct: (100 * changed) / (w * h), evidence };
  }, { a: beforePng.toString('base64'), b: afterPng.toString('base64'), evidenceName });
}

/* PNG width/height straight from the IHDR chunk — no decode needed. */
const pngSize = (buf) => [buf.readUInt32BE(16), buf.readUInt32BE(20)];

const rows = [];
let structural = 0;
for (const f of PAGE_FAMILIES) {
  for (const c of CELLS) {
    const base = `${f.id}__${c.id}`;
    const ga = JSON.parse(fs.readFileSync(path.join(beforeDir, `${base}.geometry.json`), 'utf8'));
    const gb = JSON.parse(fs.readFileSync(path.join(afterDir, `${base}.geometry.json`), 'utf8'));
    const diffs = compareGeometry(ga, gb);
    structural += diffs.length;
    const pa = fs.readFileSync(path.join(beforeDir, `${base}.png`));
    const pb = fs.readFileSync(path.join(afterDir, `${base}.png`));
    let stats;
    try {
      stats = await pixelStats(pa, pb, EVIDENCE.has(base) ? base : null);
    } catch (e) {
      // Very tall full-page captures can exceed the browser's image-decode limits. Geometry (the
      // structural gate) is unaffected; the pixel percentage is simply not available for this pair.
      stats = { sizeA: pngSize(pa), sizeB: pngSize(pb), changedPct: null, evidence: null, note: 'too large to decode in-browser' };
    }
    if (stats.evidence) fs.writeFileSync(path.join(evidenceDir, `${base}.png`), Buffer.from(stats.evidence, 'base64'));
    rows.push({ base, elements: ga.rows.length, diffs, stats });
    process.stdout.write(`  ${base}: ${diffs.length} structural diff(s), ${stats.changedPct == null ? 'pixels n/a (' + stats.note + ')' : stats.changedPct.toFixed(1) + '% pixels changed'}\n`);
  }
}
await browser.close();

const loadA = JSON.parse(fs.readFileSync(path.join(beforeDir, 'load-report.json'), 'utf8'));
const loadB = JSON.parse(fs.readFileSync(path.join(afterDir, 'load-report.json'), 'utf8'));
const loadIssues = (r) => r.filter((x) => x.captureError || (x.consoleErrors || []).length || (x.failedRequests || []).length);

let md = `# Screenshot regression — brand-colour migration\n\n`;
md += `Generated by \`scripts/compare-screenshots.mjs\`. Full-page captures (reduced motion, lazy content scrolled in) for 11 page families × desktop 1440 / mobile 390 × light / dark. `;
md += `Before = \`28bc619\` (pre-migration), after = migrated worktree. Captures stay outside git (\`scratch/\`); downscaled side-by-side evidence (left before, right after) is in \`evidence/\`.\n\n`;
md += `**Structural rule:** every element's position, size, font, visibility, image source and own text must match (±${TOL}px). Pixel change % is informational only (it is expected to be high — the colours changed).\n\n`;
md += `| Capture | Elements | Structural diffs | Size before → after | Pixels changed |\n|---|---|---|---|---|\n`;
for (const r of rows) {
  md += `| ${r.base} | ${r.elements} | ${r.diffs.length === 0 ? '0' : '**' + r.diffs.length + '**'} | ${r.stats.sizeA.join('×')} → ${r.stats.sizeB.join('×')} | ${r.stats.changedPct == null ? 'n/a — ' + r.stats.note : r.stats.changedPct.toFixed(1) + '%'} |\n`;
}
md += `\n**Total structural differences: ${structural}.**\n\n`;
md += `Load health: before ${loadIssues(loadA).length} capture(s) with console errors / failed requests; after ${loadIssues(loadB).length}.\n`;
if (structural) {
  md += `\n## Structural differences\n\n`;
  for (const r of rows.filter((x) => x.diffs.length)) {
    md += `### ${r.base}\n\n` + r.diffs.slice(0, 40).map((d) => `- ${d}`).join('\n') + (r.diffs.length > 40 ? `\n- … ${r.diffs.length - 40} more` : '') + '\n\n';
  }
}
if (loadIssues(loadB).length) {
  md += `\n## After-capture load issues\n\n` + loadIssues(loadB).map((x) => `- ${x.page} ${x.cell}: ${JSON.stringify(x)}`).join('\n') + '\n';
}
fs.writeFileSync(path.join(docDir, 'SCREENSHOT-REGRESSION.md'), md);
console.log(`\n${structural} structural difference(s) across ${rows.length} capture pairs -> docs/colour-migration/SCREENSHOT-REGRESSION.md`);
process.exit(structural ? 1 : 0);
