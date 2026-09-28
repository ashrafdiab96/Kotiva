/* Page families and viewport/mode matrix shared by the colour-migration audit scripts. */
export const PAGE_FAMILIES = [
  { id: 'home', path: '/index.html' },
  { id: 'shop', path: '/shop.html' },
  { id: 'product', path: '/product/micellar-water.html' },
  { id: 'about', path: '/about.html' },
  { id: 'science', path: '/science.html' },
  { id: 'routine-finder', path: '/routine-finder.html' },
  { id: 'journal', path: '/journal.html' },
  { id: 'contact', path: '/contact.html' },
  { id: 'ingredients', path: '/ingredients.html' },
  { id: 'privacy', path: '/privacy-policy.html' },
  { id: 'terms', path: '/terms.html' },
];

export const CELLS = [
  { id: 'desktop-light', viewport: { width: 1440, height: 900 }, mode: 'light' },
  { id: 'desktop-dark', viewport: { width: 1440, height: 900 }, mode: 'dark' },
  { id: 'm390-light', viewport: { width: 390, height: 844 }, mode: 'light' },
  { id: 'm390-dark', viewport: { width: 390, height: 844 }, mode: 'dark' },
];

/* Persist the site's own mode key before any script runs, exactly as a returning visitor would. */
export async function newModeContext(browser, cell, extra = {}) {
  const context = await browser.newContext({
    viewport: cell.viewport,
    reducedMotion: 'reduce',
    colorScheme: 'light',
    ...extra,
  });
  await context.addInitScript((mode) => {
    try { localStorage.setItem('kotiva-mode', mode); } catch (e) { /* storage blocked */ }
  }, cell.mode);
  return context;
}

/* Scroll the whole document so lazy images load and reveal classes settle, then return to top. */
export async function settle(page) {
  await page.evaluate(async () => {
    await document.fonts.ready;
    const step = Math.max(300, Math.floor(window.innerHeight * 0.8));
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo(0, document.documentElement.scrollHeight);
    await new Promise((r) => setTimeout(r, 120));
    await Promise.all([...document.images].map((img) => (img.complete ? null : img.decode().catch(() => null))));
    window.scrollTo(0, 0);
    // CSS transitions still run under reduced motion: the nav (0.3s colours, 0.35s transform) and
    // [data-theme] sections (0.6s background) must finish before anything is read or captured.
    await new Promise((r) => setTimeout(r, 900));
  });
  await page.waitForLoadState('networkidle').catch(() => {});
}
