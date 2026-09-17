/* The colour-migration scan scope (brief §3.2 / §7): css/, js/, scripts/ (incl. the Python icon
   script), assets/ SVG + text, root *.html, product/*.html. Generated output AND generator templates. */
import fs from 'node:fs';
import path from 'node:path';

export function scanFiles(root) {
  const out = [];
  const walk = (dir, filter) => {
    const abs = path.join(root, dir);
    if (!fs.existsSync(abs)) return;
    for (const ent of fs.readdirSync(abs, { withFileTypes: true })) {
      const rel = path.posix.join(dir, ent.name);
      if (ent.isDirectory()) walk(rel, filter);
      else if (filter(ent.name)) out.push(rel);
    }
  };
  walk('css', (n) => n.endsWith('.css'));
  walk('js', (n) => /\.(m?js)$/.test(n));
  walk('scripts', (n) => /\.(m?js|py)$/.test(n));
  walk('assets', (n) => /\.(svg|txt|css|json)$/i.test(n));
  for (const n of fs.readdirSync(root)) if (n.endsWith('.html')) out.push(n);
  walk('product', (n) => n.endsWith('.html'));
  return out.sort();
}
