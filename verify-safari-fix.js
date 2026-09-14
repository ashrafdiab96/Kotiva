/**
 * KOTIVA Shop — Safari Fix Verification
 * Tests local (fixed) vs live (unfixed) to confirm the patch works.
 *
 * Pass "local" or "live" as first arg:
 *   node verify-safari-fix.js local
 *   node verify-safari-fix.js live
 */

const { webkit } = require('playwright');

const TARGETS = {
  local: 'http://localhost:8787/shop.html',
  live:  'https://kotiva.co/shop.html'
};

const target = process.argv[2] || 'local';
const URL = TARGETS[target];

async function runCheck(label, url) {
  const browser = await webkit.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  console.log(`\n=== [${label}] ${url} ===`);
  await page.goto(url, { waitUntil: 'domcontentloaded' });

  // T+0ms: immediately after DOMContentLoaded
  const t0 = await page.evaluate(() => ({
    revealCount:   document.querySelectorAll('.reveal').length,
    visibleCount:  document.querySelectorAll('.reveal.visible').length,
    opacity_c0:    getComputedStyle(document.querySelector('.product-card')).opacity,
    bodyOverflowX: getComputedStyle(document.body).overflowX
  }));
  console.log(`T+0ms (DOMContentLoaded):   reveal=${t0.revealCount} visible=${t0.visibleCount} opacity[0]=${t0.opacity_c0} overflowX=${t0.bodyOverflowX}`);

  // T+100ms: RAF should have flushed in-viewport cards
  await page.waitForTimeout(100);
  const t100 = await page.evaluate(() => ({
    revealCount:  document.querySelectorAll('.reveal').length,
    visibleCount: document.querySelectorAll('.reveal.visible').length,
    opacity_c0:   getComputedStyle(document.querySelector('.product-card')).opacity,
    opacity_c1:   getComputedStyle(document.querySelectorAll('.product-card')[1]).opacity
  }));
  console.log(`T+100ms (RAF flush):        reveal=${t100.revealCount} visible=${t100.visibleCount} opacity[0]=${t100.opacity_c0} opacity[1]=${t100.opacity_c1}`);

  // T+900ms: DOMContentLoaded+800ms fail-safe should have fired
  await page.waitForTimeout(800);
  const t900 = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    return {
      revealCount:   document.querySelectorAll('.reveal').length,
      visibleCount:  document.querySelectorAll('.reveal.visible').length,
      allVisible:    Array.from(cards).every(c => c.classList.contains('visible')),
      opacity_c24:   getComputedStyle(cards[24]).opacity
    };
  });
  console.log(`T+900ms (800ms fail-safe):  reveal=${t900.revealCount} visible=${t900.visibleCount} allVisible=${t900.allVisible} opacity[24]=${t900.opacity_c24}`);

  // T+1800ms: last card (stagger=960ms delay + 850ms transition) should be nearly done
  await page.waitForTimeout(900);
  const t1800 = await page.evaluate(() => {
    const cards = document.querySelectorAll('.product-card');
    const visibleWithOpacity1 = Array.from(cards).filter(c =>
      c.classList.contains('visible') && parseFloat(getComputedStyle(c).opacity) > 0.99
    );
    return {
      fullyVisible:  visibleWithOpacity1.length,
      totalCards:    cards.length,
      opacity_c24:   getComputedStyle(cards[24]).opacity,
      opacity_c12:   getComputedStyle(cards[12]).opacity
    };
  });
  console.log(`T+1800ms (transitions):     fullyVisible=${t1800.fullyVisible}/${t1800.totalCards} opacity[24]=${t1800.opacity_c24} opacity[12]=${t1800.opacity_c12}`);

  // FILTER TEST
  await page.click('.filter-pill[data-filter="face"]');
  await page.waitForTimeout(200);
  const faceCount = await page.evaluate(() =>
    document.querySelectorAll('.product-card:not([style*="display: none"])').length
  );

  await page.click('.filter-pill[data-filter="sunscreen"]');
  await page.waitForTimeout(200);
  const spfCount = await page.evaluate(() =>
    document.querySelectorAll('.product-card:not([style*="display: none"])').length
  );

  await page.click('.filter-pill[data-filter="all"]');
  await page.waitForTimeout(200);
  const allCount = await page.evaluate(() =>
    document.querySelectorAll('.product-card:not([style*="display: none"])').length
  );

  console.log(`Filters:                    face=${faceCount}/16 spf=${spfCount}/3 all=${allCount}/25`);

  // HAMBURGER: verify overflow-Y set (not overflow)
  const hamburger = page.locator('.nav-hamburger');
  if (await hamburger.count()) {
    await hamburger.click();
    await page.waitForTimeout(100);
    const overflowAfterOpen = await page.evaluate(() => ({
      overflowY:    document.body.style.overflowY,
      overflow:     document.body.style.overflow,
      overflowX:    document.body.style.overflowX,
      computedOvfX: getComputedStyle(document.body).overflowX
    }));
    console.log(`Hamburger open:             overflowY="${overflowAfterOpen.overflowY}" overflow="${overflowAfterOpen.overflow}" overflowX="${overflowAfterOpen.overflowX}" computedOverflowX=${overflowAfterOpen.computedOvfX}`);

    // Close the nav
    await hamburger.click();
    await page.waitForTimeout(100);
    const overflowAfterClose = await page.evaluate(() => ({
      overflowY:    document.body.style.overflowY,
      overflow:     document.body.style.overflow,
      computedOvfX: getComputedStyle(document.body).overflowX
    }));
    console.log(`Hamburger closed:           overflowY="${overflowAfterClose.overflowY}" overflow="${overflowAfterClose.overflow}" computedOverflowX=${overflowAfterClose.computedOvfX}`);
  }

  // IMAGES
  const imgState = await page.evaluate(() => {
    const imgs = document.querySelectorAll('img');
    const loaded = Array.from(imgs).filter(i => i.complete && i.naturalWidth > 0).length;
    return { total: imgs.length, loaded };
  });
  console.log(`Images:                     ${imgState.loaded}/${imgState.total} loaded`);

  if (errors.length) console.log('JS ERRORS:', errors);
  else console.log('JS errors:                  none');

  await browser.close();
}

(async () => {
  await runCheck('FIXED (local)', URL);
  console.log('\n=== DONE ===\n');
})();
