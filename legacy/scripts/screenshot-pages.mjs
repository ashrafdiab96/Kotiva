#!/usr/bin/env node
/* ============================================================
   Screenshot + geometry capture for the brand-colour migration (brief §6.1 / §6.7).

   For every page family x {desktop, 390px} x {light, dark}: a FULL-PAGE screenshot plus a
   geometry snapshot of every element (position, size, font, visibility, image source, own
   text). The geometry is what decides "colour-only": a pixel diff alone cannot tell a colour
   change from a 1px shift, but the element boxes can.

   Also records console errors, page errors and failed/404 requests per page+mode.

   Usage: node scripts/screenshot-pages.mjs --label before|after [--out scratch/colour-migration]
   Output stays outside git (scratch/ is ignored).
   ============================================================ */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { startServer } from './lib/static-server.mjs';
import { PAGE_FAMILIES, CELLS, newModeContext, settle } from './lib/pages.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, def) => {
  const i = process.argv.indexOf(`--${name}`);
  return i === -1 ? def : process.argv[i + 1];
};
const label = arg('label', 'capture');
const outDir = path.resolve(ROOT, arg('out', 'scratch/colour-migration'), label);
// --root serves another checkout (e.g. a git worktree of the pre-migration commit) with this script.
const serveRoot = path.resolve(ROOT, arg('root', '.'));
fs.mkdirSync(outDir, { recursive: true });

const server = await startServer(serveRoot);
const browser = await chromium.launch();
const loadReport = [];

async function capture(family, cell) {
  const context = await newModeContext(browser, cell);
  const page = await context.newPage();
  const errors = [];
  const failed = [];
  page.on('console', (msg) => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', (err) => errors.push(String(err)));
  page.on('response', (res) => { if (res.status() >= 400 && res.url().startsWith(server.origin)) failed.push(`${res.status()} ${res.url().slice(server.origin.length)}`); });
  page.on('requestfailed', (req) => { if (req.url().startsWith(server.origin)) failed.push(`FAILED ${req.url().slice(server.origin.length)}`); });

  await page.goto(server.origin + family.path, { waitUntil: 'networkidle' });
  await settle(page);

  const geometry = await page.evaluate(() => {
    const rows = [];
    const round = (n) => Math.round(n * 2) / 2;
    let i = 0;
    for (const el of document.body.querySelectorAll('*')) {
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      let own = '';
      for (const n of el.childNodes) if (n.nodeType === 3) own += n.textContent;
      rows.push({
        i: i++,
        tag: el.tagName.toLowerCase(),
        cls: typeof el.className === 'string' ? el.className : (el.className && el.className.baseVal) || '',
        x: round(r.left + window.scrollX),
        y: round(r.top + window.scrollY),
        w: round(r.width),
        h: round(r.height),
        font: `${cs.fontFamily}|${cs.fontSize}|${cs.fontWeight}|${cs.fontStyle}|${cs.lineHeight}|${cs.letterSpacing}|${cs.textTransform}`,
        vis: `${cs.display}|${cs.visibility}|${cs.opacity}`,
        src: el.tagName === 'IMG' ? el.currentSrc.replace(location.origin, '') : '',
        text: own.replace(/\s+/g, ' ').trim().slice(0, 80),
      });
    }
    return {
      scrollHeight: document.documentElement.scrollHeight,
      scrollWidth: document.documentElement.scrollWidth,
      htmlMode: document.documentElement.dataset.mode || 'light',
      rows,
    };
  });

  const base = `${family.id}__${cell.id}`;
  fs.writeFileSync(path.join(outDir, `${base}.geometry.json`), JSON.stringify(geometry));
  await page.screenshot({ path: path.join(outDir, `${base}.png`), fullPage: true, animations: 'disabled' });
  loadReport.push({ page: family.id, cell: cell.id, consoleErrors: errors, failedRequests: [...new Set(failed)] });
  await context.close();
  process.stdout.write(`  captured ${base}\n`);
}

const jobs = [];
for (const f of PAGE_FAMILIES) for (const c of CELLS) jobs.push([f, c]);
const POOL = 4;
let next = 0;
await Promise.all(Array.from({ length: POOL }, async () => {
  while (next < jobs.length) {
    const [f, c] = jobs[next++];
    try {
      await capture(f, c);
    } catch (e) {
      loadReport.push({ page: f.id, cell: c.id, captureError: String(e) });
      process.stdout.write(`  ERROR ${f.id}__${c.id}: ${e}\n`);
    }
  }
}));

loadReport.sort((a, b) => (a.page + a.cell).localeCompare(b.page + b.cell));
fs.writeFileSync(path.join(outDir, 'load-report.json'), JSON.stringify(loadReport, null, 2));
await browser.close();
await server.close();
const errs = loadReport.filter((r) => r.captureError || (r.consoleErrors && r.consoleErrors.length) || (r.failedRequests && r.failedRequests.length));
console.log(`\n${jobs.length} captures -> ${path.relative(ROOT, outDir)}; ${errs.length} with console errors / failed requests / capture errors`);
