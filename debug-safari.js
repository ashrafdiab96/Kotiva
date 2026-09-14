/**
 * KOTIVA Shop — Safari/WebKit Diagnostic
 * Phase 1: Evidence gathering ONLY. No fixes applied.
 *
 * Measures:
 *   1. How many product cards exist in DOM at page load
 *   2. Computed visibility of cards immediately after load
 *   3. Body/html overflow-x computed value (DEC-147 check)
 *   4. Whether IntersectionObserver fires for in-viewport cards
 *   5. State after the 2500ms fail-safe window
 *   6. Filter pill behaviour
 *   7. Console errors
 *   8. Lazy image load state
 */

const { webkit } = require('playwright');

const URL = 'https://kotiva.co/shop.html';

(async () => {
  const browser = await webkit.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },   // iPhone 14 Pro
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();

  // Capture all console output and errors
  const consoleLog = [];
  const pageErrors = [];
  page.on('console', msg => consoleLog.push({ type: msg.type(), text: msg.text() }));
  page.on('pageerror', err => pageErrors.push(err.message));

  console.log('\n=== DIAGNOSTIC RUN: WebKit (iPhone 14 Pro viewport) ===\n');

  await page.goto(URL, { waitUntil: 'domcontentloaded' });

  // --- PROBE 1: Immediate post-DOMContentLoaded state ---
  const immediateState = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    const grid = document.getElementById('product-grid');
    return {
      cardCount: cards.length,
      gridExists: !!grid,
      firstThreeCards: Array.from(cards).slice(0, 3).map((c, i) => ({
        index: i,
        hasReveal: c.classList.contains('reveal'),
        hasVisible: c.classList.contains('visible'),
        opacity: getComputedStyle(c).opacity,
        display: getComputedStyle(c).display,
        transitionDelay: c.style.transitionDelay
      })),
      bodyOverflowX: getComputedStyle(document.body).overflowX,
      htmlOverflowX: getComputedStyle(document.documentElement).overflowX,
      bodyInlineOverflow: document.body.style.overflow,
      hasIO: typeof IntersectionObserver !== 'undefined',
      revealCount: document.querySelectorAll('.reveal').length,
      visibleCount: document.querySelectorAll('.reveal.visible').length
    };
  });

  console.log('--- PROBE 1: Immediately after DOMContentLoaded ---');
  console.log(JSON.stringify(immediateState, null, 2));

  // --- PROBE 2: Wait for animations.js to run (it listens on DOMContentLoaded too) ---
  await page.waitForTimeout(200);
  const afterAnimInit = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    return {
      revealCount: document.querySelectorAll('.reveal').length,
      visibleCount: document.querySelectorAll('.reveal.visible').length,
      firstCard: {
        hasReveal: cards[0]?.classList.contains('reveal'),
        hasVisible: cards[0]?.classList.contains('visible'),
        opacity: cards[0] ? getComputedStyle(cards[0]).opacity : 'N/A'
      }
    };
  });
  console.log('\n--- PROBE 2: 200ms after DOMContentLoaded (anim.js should be done) ---');
  console.log(JSON.stringify(afterAnimInit, null, 2));

  // --- PROBE 3: After window.load fires ---
  await page.waitForLoadState('load');
  const afterLoad = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    return {
      visibleCount: document.querySelectorAll('.reveal.visible').length,
      revealCount: document.querySelectorAll('.reveal').length,
      firstCard: {
        hasVisible: cards[0]?.classList.contains('visible'),
        opacity: cards[0] ? getComputedStyle(cards[0]).opacity : 'N/A'
      },
      bodyInlineOverflow: document.body.style.overflow
    };
  });
  console.log('\n--- PROBE 3: After window.load ---');
  console.log(JSON.stringify(afterLoad, null, 2));

  // --- PROBE 4: Wait 800ms past window.load (before 2500ms fail-safe) ---
  await page.waitForTimeout(800);
  const beforeFailSafe = await page.evaluate(() => {
    return {
      visibleCount: document.querySelectorAll('.reveal.visible').length,
      opacity_card0: (() => {
        const c = document.querySelector('.product-card');
        return c ? getComputedStyle(c).opacity : 'N/A';
      })()
    };
  });
  console.log('\n--- PROBE 4: 800ms after window.load (before 2500ms fail-safe) ---');
  console.log(JSON.stringify(beforeFailSafe, null, 2));

  // --- PROBE 5: After 2500ms fail-safe should have fired ---
  await page.waitForTimeout(2800);
  const afterFailSafe = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    return {
      visibleCount: document.querySelectorAll('.reveal.visible').length,
      revealCount: document.querySelectorAll('.reveal').length,
      allVisible: Array.from(cards).every(c => c.classList.contains('visible')),
      opacity_card0: cards[0] ? getComputedStyle(cards[0]).opacity : 'N/A',
      opacity_card10: cards[10] ? getComputedStyle(cards[10]).opacity : 'N/A'
    };
  });
  console.log('\n--- PROBE 5: 2800ms after window.load (2500ms fail-safe should have fired) ---');
  console.log(JSON.stringify(afterFailSafe, null, 2));

  // --- PROBE 6: Scroll the page — does IO fire? ---
  await page.evaluate(() => window.scrollTo(0, 300));
  await page.waitForTimeout(300);
  const afterScroll = await page.evaluate(() => ({
    visibleCount: document.querySelectorAll('.reveal.visible').length
  }));
  console.log('\n--- PROBE 6: After scrolling 300px ---');
  console.log(JSON.stringify(afterScroll, null, 2));

  // --- PROBE 7: Filter pill test ---
  // Reset scroll and test "Face" filter
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(200);
  const beforeFilter = await page.evaluate(() => ({
    visibleCards: document.querySelectorAll('.product-card:not([style*="display: none"])').length
  }));

  await page.click('.filter-pill[data-filter="face"]');
  await page.waitForTimeout(300);
  const afterFaceFilter = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    const shown = Array.from(cards).filter(c => c.style.display !== 'none');
    return {
      totalCards: cards.length,
      shownAfterFaceFilter: shown.length,
      expectedFaceCount: 16
    };
  });
  console.log('\n--- PROBE 7: Filter pills ---');
  console.log('Before filter:', JSON.stringify(beforeFilter));
  console.log('After "Face" filter:', JSON.stringify(afterFaceFilter));

  // Test SPF filter (checking for the tag mismatch bug)
  await page.click('.filter-pill[data-filter="sunscreen"]');
  await page.waitForTimeout(300);
  const afterSPFFilter = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    const shown = Array.from(cards).filter(c => c.style.display !== 'none');
    return {
      shownAfterSPFFilter: shown.length,
      expectedSPFCount: 3,
      shownNames: shown.map(c => c.querySelector('.product-card-name')?.textContent)
    };
  });
  console.log('After "SPF" filter:', JSON.stringify(afterSPFFilter));

  // --- PROBE 8: Lazy image load state ---
  const imageState = await page.evaluate(() => {
    const imgs = document.querySelectorAll('img[loading="lazy"], img[data-k-loaded]');
    const loaded = Array.from(imgs).filter(img => img.complete && img.naturalWidth > 0);
    const unloaded = Array.from(imgs).filter(img => !img.complete || img.naturalWidth === 0);
    return {
      totalLazyImgs: document.querySelectorAll('img').length,
      loadedCount: loaded.length,
      unloadedCount: unloaded.length,
      unloadedSrcs: unloaded.slice(0, 5).map(img => img.getAttribute('src'))
    };
  });
  console.log('\n--- PROBE 8: Lazy image load state ---');
  console.log(JSON.stringify(imageState, null, 2));

  // --- PROBE 9: Overflow-x at end of run ---
  const overflowFinal = await page.evaluate(() => ({
    bodyComputedOverflowX: getComputedStyle(document.body).overflowX,
    bodyInlineOverflow: document.body.style.overflow,
    htmlComputedOverflowX: getComputedStyle(document.documentElement).overflowX
  }));
  console.log('\n--- PROBE 9: Overflow state (DEC-147 check) ---');
  console.log(JSON.stringify(overflowFinal, null, 2));

  // --- Console errors summary ---
  console.log('\n--- Console output ---');
  const errors = consoleLog.filter(m => m.type === 'error');
  const warnings = consoleLog.filter(m => m.type === 'warning');
  if (errors.length) errors.forEach(m => console.log('ERROR:', m.text));
  else console.log('No console errors.');
  if (warnings.length) warnings.forEach(m => console.log('WARN:', m.text));

  if (pageErrors.length) {
    console.log('\n--- Page JS errors ---');
    pageErrors.forEach(e => console.log('JS ERROR:', e));
  } else {
    console.log('No JS page errors.');
  }

  await browser.close();
  console.log('\n=== DIAGNOSTIC COMPLETE ===\n');
})();
