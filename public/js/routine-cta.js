/* ============================================================
   KOTIVA™ — Routine Finder → Salla handoff links
   Plain script (no modules): the page reads window.KOTIVA_ROUTINE_CTA,
   the Node test harness require()s it.

   Routine-model id N is SKU KOT{N:03} (D-39). The SKU is derived here
   from the model id, never looked up by database id: the two only
   coincide by accident of auto-increment.
   ============================================================ */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.KOTIVA_ROUTINE_CTA = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /* Routine-model id → Salla SKU, or null for anything that is not an
     integer 1..999 (so a bad id is skipped, not turned into "KOTNaN"). */
  function skuOf(id) {
    var n = typeof id === 'string' && /^\d+$/.test(id) ? Number(id) : id;
    if (typeof n !== 'number' || n % 1 !== 0 || n < 1 || n > 999) return null;
    return 'KOT' + String(n).padStart(3, '0');
  }

  /* Unique SKUs of a build() result, in am → pm → anytime order. */
  function skusOf(result) {
    var skus = [];
    ['am', 'pm', 'anytime'].forEach(function (half) {
      ((result && result[half]) || []).forEach(function (s) {
        var sku = s ? skuOf(s.id) : null;
        if (sku && skus.indexOf(sku) === -1) skus.push(sku);
      });
    });
    return skus;
  }

  /* Mirrors App\Support\Salla::listingUrl(null, $skus): the store root
     alone with no SKUs, otherwise root + '/?routine=' + comma-joined
     upper-case SKUs. Commas stay literal — the Salla theme splits on ",". */
  function listingUrl(storeUrl, skus) {
    var clean = [];
    (skus || []).forEach(function (s) {
      if (typeof s !== 'string') return;
      s = s.trim().toUpperCase();
      if (/^[A-Z0-9_-]{1,64}$/.test(s) && clean.indexOf(s) === -1) clean.push(s);
    });
    return clean.length
      ? storeUrl + '/?routine=' + clean.map(encodeURIComponent).join(',')
      : storeUrl;
  }

  /* The "Shop These Products" href for a build() result. */
  function ctaHref(storeUrl, result) {
    return listingUrl(storeUrl, skusOf(result));
  }

  return { skuOf: skuOf, skusOf: skusOf, listingUrl: listingUrl, ctaHref: ctaHref };
}));
