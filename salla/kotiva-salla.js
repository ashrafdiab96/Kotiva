(() => {
  const MAIN_SITE = 'https://klyvosystems.com';
  const STORE = window.location.origin;

  function initKotivaLayout() {
    if (document.getElementById('kotiva-salla-header')) return;

    /* =====================================================
       HEADER
       ===================================================== */

    const header = `
      <header id="kotiva-salla-header">
        <div class="kotiva-nav-inner">

          <a
            class="kotiva-nav-logo"
            href="${MAIN_SITE}/"
            aria-label="KOTIVA Home"
          >
            <img
              src="${MAIN_SITE}/assets/kotiva-logo.svg"
              alt="KOTIVA"
            >
          </a>

          <nav class="kotiva-nav-links" aria-label="Main navigation">

            <a class="kotiva-nav-link"
               href="${MAIN_SITE}/">
              Home
            </a>

            <a class="kotiva-nav-link"
               href="${STORE}/">
              Shop
            </a>

            <a class="kotiva-nav-link"
               href="${MAIN_SITE}/science">
              Science
            </a>

            <a class="kotiva-nav-link"
               href="${MAIN_SITE}/journal">
              Journal
            </a>

            <a class="kotiva-nav-link"
               href="${MAIN_SITE}/about">
              About
            </a>

            <a class="kotiva-nav-link"
               href="${MAIN_SITE}/contact">
              Where to Buy
            </a>

          </nav>

          <div class="kotiva-nav-right">

            <a
              class="kotiva-routine-button"
              href="${MAIN_SITE}/routine-finder"
            >
              Find My Routine
            </a>

            <a
                class="kotiva-cart-button"
                href="${STORE}/cart"
                aria-label="Shopping cart"
            >
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <path d="M3 6h18"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </a>

            <button
              id="kotiva-menu-button"
              class="kotiva-menu-button"
              type="button"
              aria-label="Open menu"
              aria-expanded="false"
            >
              <span></span>
              <span></span>
              <span></span>
            </button>

          </div>

        </div>
      </header>
    `;


    /* =====================================================
       MOBILE MENU
       ===================================================== */

    const mobileMenu = `
      <nav
        id="kotiva-mobile-menu"
        aria-label="Mobile navigation"
      >

        <a href="${MAIN_SITE}/">Home</a>

        <a href="${STORE}/">Shop</a>

        <a href="${MAIN_SITE}/science">Science</a>

        <a href="${MAIN_SITE}/journal">Journal</a>

        <a href="${MAIN_SITE}/about">About</a>

        <a href="${MAIN_SITE}/contact">
          Where to Buy
        </a>

        <a href="${MAIN_SITE}/routine-finder">
          Find My Routine
        </a>

      </nav>
    `;


    /* =====================================================
       FOOTER
       ===================================================== */

    const footer = `
      <footer id="kotiva-salla-footer">

        <div class="kotiva-footer-container">

          <div class="kotiva-footer-main">

            <!-- BRAND -->
            <div>

              <a href="${MAIN_SITE}/">

                <img
                  class="kotiva-footer-logo"
                  src="${MAIN_SITE}/assets/kotiva-logo.svg"
                  alt="KOTIVA"
                >

              </a>

              <p class="kotiva-footer-tagline">
                Doctor-approved skincare.
                Perfected by science.
                Tailored for you.
              </p>

              <div class="kotiva-social">

                <!-- Instagram -->
                <span class="kotiva-social-icon"
                      aria-label="Instagram">

                  <svg width="14"
                       height="14"
                       viewBox="0 0 24 24"
                       fill="none"
                       stroke="currentColor"
                       stroke-width="1.5">

                    <rect x="2" y="2"
                          width="20"
                          height="20"
                          rx="5"></rect>

                    <circle cx="12"
                            cy="12"
                            r="4"></circle>

                    <circle cx="17.5"
                            cy="6.5"
                            r=".5"
                            fill="currentColor"></circle>

                  </svg>

                </span>


                <!-- TikTok -->
                <span class="kotiva-social-icon"
                      aria-label="TikTok">

                  <svg width="14"
                       height="14"
                       viewBox="0 0 24 24"
                       fill="currentColor">

                    <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.16 8.16 0 004.77 1.52V6.75a4.85 4.85 0 01-1-.06z"></path>

                  </svg>

                </span>


                <!-- Facebook -->
                <span class="kotiva-social-icon"
                      aria-label="Facebook">

                  <svg width="14"
                       height="14"
                       viewBox="0 0 24 24"
                       fill="currentColor">

                    <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path>

                  </svg>

                </span>

              </div>

              <span class="kotiva-social-soon">
                Coming soon
              </span>

            </div>


            <!-- SHOP -->
            <div>

              <div class="kotiva-footer-title">
                Shop
              </div>

              <nav class="kotiva-footer-links">

                <a href="${STORE}/">
                  All Products
                </a>

                <a href="${STORE}/">
                  Face Care
                </a>

                <a href="${STORE}/">
                  Body Care
                </a>

                <a href="${STORE}/">
                  Serums
                </a>

                <a href="${STORE}/">
                  SPF
                </a>

                <a href="${STORE}/">
                  Hair
                </a>

              </nav>

            </div>


            <!-- COMPANY -->
            <div>

              <div class="kotiva-footer-title">
                Company
              </div>

              <nav class="kotiva-footer-links">

                <a href="${MAIN_SITE}/about">
                  About Kotiva
                </a>

                <a href="${MAIN_SITE}/science">
                  Our Science
                </a>

                <a href="${MAIN_SITE}/ingredients">
                  Ingredient Glossary
                </a>

                <a href="${MAIN_SITE}/routine-finder">
                  Routine Finder
                </a>

                <a href="${MAIN_SITE}/journal">
                  Journal
                </a>

                <a href="${MAIN_SITE}/#newsletter">
                  Newsletter
                </a>

                <a href="${MAIN_SITE}/contact">
                  Contact Us
                </a>

                <a
                  href="${MAIN_SITE}/assets/kotiva-company-profile.pdf"
                  target="_blank"
                  rel="noopener"
                >
                  Company Profile (PDF)
                </a>

              </nav>

            </div>


            <!-- SUPPORT -->
            <div>

              <div class="kotiva-footer-title">
                Support
              </div>

              <nav class="kotiva-footer-links">

                <a href="${MAIN_SITE}/privacy-policy">
                  Privacy Policy
                </a>

                <a href="${MAIN_SITE}/terms">
                  Terms of Use
                </a>

                <a href="${MAIN_SITE}/contact#faq">
                  FAQ
                </a>

              </nav>

            </div>

          </div>


          <div class="kotiva-footer-bottom">

            <span>
              © 2026 KOTIVA™, a brand of Vitakode LLC.
              All rights reserved.
            </span>

            <span>
              Doctor-approved skincare tailored for you.
            </span>

          </div>

        </div>

      </footer>
    `;


    /* ADD TO PAGE */

    document.body.insertAdjacentHTML(
      'afterbegin',
      header + mobileMenu
    );

    document.body.insertAdjacentHTML(
      'beforeend',
      footer
    );

    document.body.classList.add(
      'kotiva-salla-ready'
    );


    /* =====================================================
       MOBILE MENU LOGIC
       ===================================================== */

    const button =
      document.getElementById('kotiva-menu-button');

    const menu =
      document.getElementById('kotiva-mobile-menu');

    button?.addEventListener('click', () => {

      const open =
        menu.classList.toggle('is-open');

      button.setAttribute(
        'aria-expanded',
        String(open)
      );

      document.body.classList.toggle(
        'kotiva-menu-open',
        open
      );

    });


    menu?.querySelectorAll('a').forEach(link => {

      link.addEventListener('click', () => {

        menu.classList.remove('is-open');

        document.body.classList.remove(
          'kotiva-menu-open'
        );

        button?.setAttribute(
          'aria-expanded',
          'false'
        );

      });

    });

  }


  /* Run safely after DOM loads */

  if (document.readyState === 'loading') {

    document.addEventListener(
      'DOMContentLoaded',
      initKotivaLayout
    );

  } else {

    initKotivaLayout();

  }

})();

/* =========================================================
   KOTIVA SALLA — LISTING + PDP V6
   Direct storefront API stability rewrite
   - Listing uses ONE Salla products-list forced to source=latest, limit=25
   - No native pagination racing / no DOM-page accumulation
   - Client-side 15 + 10 Load More, filters, and sort
   - Product URLs come from card.product.url first
   - PDP product id supports Salla /p123 URL format
   - Native PDP sliders are removed; one same-category slider only
   - Empty/failed similar-products section is removed completely
   ========================================================= */

(() => {
  const ROOT_PATH = '/';
  const EXPECTED_PRODUCTS = 25;
  const INITIAL_VISIBLE = 15;
  const LOAD_MORE_STEP = 10;
  const MAIN_SITE = 'https://klyvosystems.com';

  const PRODUCTS = [
    { name:'micellar water', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'All Skin Types', concern:'Makeup Removal', featured:true, slug:'micellar-water', image:'assets/products/kot001-micellar-water.webp' },
    { name:'acne cleansing gel', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'Oily / Acne-Prone', concern:'Acne & Breakouts', featured:true, slug:'acne-cleansing-gel', image:'assets/products/kot002-acne-cleansing-gel.webp' },
    { name:'hyaluronic facial cleanser', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'All Skin Types', concern:'Hydration', featured:false, slug:'hyaluronic-facial-cleanser', image:'assets/products/kot003-hyaluronic-facial-cleanser.webp' },
    { name:'facial foam for sensitive skin', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'Sensitive Skin', concern:'Soothing & Calming', featured:false, slug:'facial-foam-sensitive', image:'assets/products/kot004-facial-foam-sensitive.webp' },
    { name:'glow up water essence toner', filter:'toner', category:'Toner', relatedCategory:'Face Care', skin:'All Skin Types', concern:'Glow & Radiance', featured:true, slug:'glow-up-water-essence-toner', image:'assets/products/kot005-glow-up-water-essence-toner.webp' },
    { name:'oil control foam', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'Oily Skin', concern:'Oil Control', featured:false, slug:'oil-control-foam', image:'assets/products/kot006-oil-control-foam.webp' },
    { name:'face & body cleansing foam', filter:'cleanser', category:'Cleanser', relatedCategory:'Face Care', skin:'All Skin Types', concern:'Daily Cleansing', featured:false, slug:'face-body-cleansing-foam', image:'assets/products/kot007-face-body-cleansing-foam.webp' },
    { name:'post fillers lip balm', filter:'lip-care', category:'Lip Care', relatedCategory:'Face Care', skin:'All Skin Types', concern:'Lip Care', featured:false, slug:'post-fillers-lip-balm', image:'assets/products/kot008-post-fillers-lip-balm.webp' },
    { name:'sun protection spf 50+', filter:'spf', category:'SPF', relatedCategory:'SPF', skin:'All Skin Types', concern:'Sun Protection', featured:true, slug:'sun-protection-spf50', image:'assets/products/kot009-sun-protection-spf50.webp' },
    { name:'after sun cream', filter:'body', category:'Body', relatedCategory:'Body Care', skin:'All Skin Types', concern:'After-Sun Soothing', featured:false, slug:'after-sun-cream', image:'assets/products/kot010-after-sun-cream.webp' },
    { name:'younger hand cream', filter:'hand-care', category:'Hand Care', relatedCategory:'Body Care', skin:'All Skin Types', concern:'Anti-Aging', featured:false, slug:'younger-hand-cream', image:'assets/products/kot011-younger-hand-cream.webp' },
    { name:'whitening hand cream', filter:'hand-care', category:'Hand Care', relatedCategory:'Body Care', skin:'All Skin Types', concern:'Brightening', featured:false, slug:'whitening-hand-cream', image:'assets/products/kot012-whitening-hand-cream.webp' },
    { name:'anti-perspirant roll on', filter:'body', category:'Body', relatedCategory:'Body Care', skin:'All Skin Types', concern:'Anti-Perspirant', featured:false, slug:'anti-perspirant-roll-on', image:'assets/products/kot013-anti-perspirant-roll-on.webp' },
    { name:'whitening anti-perspirant', filter:'body', category:'Body', relatedCategory:'Body Care', skin:'All Skin Types', concern:'Brightening', featured:false, slug:'whitening-anti-perspirant', image:'assets/products/kot014-whitening-anti-perspirant.webp' },
    { name:'hydroshield post-laser cream', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'Post-Procedure', concern:'Barrier Repair', featured:false, slug:'hydroshield-post-laser-cream', image:'assets/products/kot015-hydroshield-post-laser-cream.webp' },
    { name:'triple action whitening day cream', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'All Skin Types', concern:'Brightening', featured:true, slug:'whitening-day-cream-spf50', image:'assets/products/kot016-whitening-day-cream-spf50.webp' },
    { name:'triple action whitening night cream', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'All Skin Types', concern:'Brightening', featured:false, slug:'whitening-night-cream', image:'assets/products/kot017-whitening-night-cream.webp' },
    { name:'triple action bikini area whitening cream', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'Sensitive Area', concern:'Brightening', featured:false, slug:'bikini-area-whitening-cream', image:'assets/products/kot018-bikini-area-whitening-cream.webp' },
    { name:'triple action whitening wash gel for sensitive area', filter:'cleanser', category:'Cleanser', relatedCategory:'Body Care', skin:'Sensitive Area', concern:'Brightening', featured:false, slug:'whitening-wash-gel-sensitive', image:'assets/products/kot019-whitening-wash-gel-sensitive.webp' },
    { name:'acne control', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'Oily / Acne-Prone', concern:'Acne & Breakouts', featured:true, slug:'acne-control', image:'assets/products/kot020-acne-control.webp' },
    { name:'pores off serum', filter:'serums', category:'Serums', relatedCategory:'Serums', skin:'Oily / Combination', concern:'Pores & Texture', featured:false, slug:'pores-off-serum', image:'assets/products/kot021-pores-off-serum.webp' },
    { name:'uv balance spf 50+', filter:'spf', category:'SPF', relatedCategory:'SPF', skin:'Oily / Acne-Prone', concern:'Sun Protection', featured:true, slug:'uv-balance-spf50', image:'assets/products/kot022-uv-balance-spf50.webp' },
    { name:'anti-hair loss ampoules', filter:'hair', category:'Hair', relatedCategory:'Hair', skin:'All Hair Types', concern:'Hair Loss', featured:false, slug:'anti-hair-loss-ampoules', image:'assets/products/kot023-anti-hair-loss-ampoules.webp' },
    { name:'anti-hair loss shampoo', filter:'hair', category:'Hair', relatedCategory:'Hair', skin:'All Hair Types', concern:'Hair Loss', featured:false, slug:'anti-hair-loss-shampoo', image:'assets/products/kot024-anti-hair-loss-shampoo.webp' },
    { name:'siliscar gel', filter:'treatment', category:'Treatment', relatedCategory:'Treatments', skin:'Scar-Prone', concern:'Scars & Barrier', featured:false, slug:'siliscar-gel', image:'assets/products/kot025-siliscar-gel.webp' }
  ].map((item, index) => ({ ...item, order:index + 1 }));

  const FILTERS = [
    ['all','All'], ['cleanser','Cleanser'], ['toner','Toner'], ['lip-care','Lip Care'],
    ['spf','SPF'], ['body','Body'], ['hand-care','Hand Care'], ['treatment','Treatment'],
    ['serums','Serums'], ['hair','Hair']
  ];

  const normalize = (value) => String(value || '')
    .toLowerCase().replace(/^kotiva\s+/i, '').replace(/\s+/g, ' ').trim();
  const metaMap = new Map(PRODUCTS.map((item) => [normalize(item.name), item]));
  const cleanPath = () => location.pathname.replace(/\/+$/, '') || '/';

  function isProductPage() {
    return /(?:-|\/)p\d+(?=\/|$)/i.test(location.pathname) || Boolean(document.querySelector(
      '[data-testid="store-product-title"],[data-testid="store-product-form"],[data-testid="store-product-image-slider"]'
    ));
  }

  function getProductData(card) {
    if (!card) return null;
    let value = card.product;
    if (!value) value = card.getAttribute?.('product');
    if (typeof value === 'string') {
      try { value = JSON.parse(value); } catch (_) { value = null; }
    }
    return value && typeof value === 'object' ? value : null;
  }

  function getTitleElement(card) {
    return card?.querySelector?.('.s-product-card-content-title') || null;
  }

  function getCardTitleLink(card) {
    return getTitleElement(card)?.querySelector('a') || null;
  }

  function isSallaProductHref(href) {
    return /(?:-|\/)p\d+(?=\/|$|[?#])/i.test(String(href || ''));
  }

  function getCardHref(card) {
    const product = getProductData(card);
    if (product?.url) return String(product.url);
    const titleLink = getCardTitleLink(card);
    if (titleLink?.href) return titleLink.href;
    const anyProductLink = Array.from(card?.querySelectorAll?.('a[href]') || [])
      .find((a) => isSallaProductHref(a.getAttribute('href') || a.href));
    return anyProductLink?.href || '';
  }

  function getCardName(card) {
    const product = getProductData(card);
    if (product?.name) return normalize(product.name);
    const title = getTitleElement(card);
    return normalize(title?.textContent || getCardTitleLink(card)?.textContent);
  }

  function getMeta(cardOrName) {
    const name = typeof cardOrName === 'string' ? normalize(cardOrName) : getCardName(cardOrName);
    return metaMap.get(name) || null;
  }

  function getPrice(card) {
    const product = getProductData(card);
    const raw = product?.sale_price || product?.price || product?.regular_price;
    if (Number.isFinite(Number(raw))) return Number(raw);
    const el = card.querySelector('.s-product-card-price,.s-product-card-sale-price h4');
    const match = (el?.textContent || '').replace(/,/g, '').match(/[\d.]+/);
    return match ? Number(match[0]) : 0;
  }

  function styleNativeAddButton(host, mode='outline') {
    if (!host) return;
    host.setAttribute('width','wide');
    if (mode === 'outline') host.setAttribute('fill','outline');
    host.classList.add('kv-add-btn', mode === 'solid' ? 'kv-add-btn-solid' : 'kv-add-btn-outline');
    const apply = () => {
      if (!host.shadowRoot || host.shadowRoot.getElementById('kv-native-button-style')) return;
      const style = document.createElement('style');
      style.id = 'kv-native-button-style';
      style.textContent = `
        button,.s-button-element{width:100%!important;min-height:44px!important;border-radius:999px!important;
        border:1px solid rgba(35,31,32,.32)!important;background:${mode === 'solid' ? '#231F20' : 'transparent'}!important;
        color:${mode === 'solid' ? '#fff' : '#231F20'}!important;box-shadow:none!important;font-size:10px!important;
        line-height:1!important;font-weight:700!important;letter-spacing:.12em!important;text-transform:uppercase!important}
        button:hover,.s-button-element:hover{background:#231F20!important;border-color:#231F20!important;color:#fff!important}`;
      host.shadowRoot.appendChild(style);
    };
    apply(); setTimeout(apply,250); setTimeout(apply,800);
  }

  /* -------------------- V6 CATALOG + LISTING -------------------- */
  const CATALOG_CACHE_KEY = 'kotiva:salla:catalog:v6';
  let catalogPromise = null;
  let catalogProducts = [];
  let activeFilter = 'all';
  let activeSort = 'featured';
  let visibleLimit = INITIAL_VISIBLE;
  let listingReady = false;
  let listingPreparing = false;

  function createHero() {
    return `<section id="kv-shop-hero"><div class="kv-shop-hero-inner">
      <p class="kv-shop-eyebrow">Doctor-Approved Skincare</p>
      <h1 class="kv-shop-title">The Collection</h1>
      <p class="kv-shop-description">25 products formulated with clinically proven ingredients</p>
    </div></section>`;
  }

  function createControls(products = []) {
    const counts = Object.fromEntries(FILTERS.map(([key]) => [key, 0]));
    products.forEach((product) => {
      const meta = getMeta(product?.name || '');
      if (meta && counts[meta.filter] !== undefined) counts[meta.filter] += 1;
    });
    counts.all = products.length || PRODUCTS.length;
    const filters = FILTERS.map(([key, label]) =>
      `<button type="button" class="kv-filter ${key === 'all' ? 'active' : ''}" data-kv-filter="${key}">${label} (${counts[key] || 0})</button>`
    ).join('');
    return `<section id="kv-shop-controls"><div class="kv-shop-controls-inner">
      <div class="kv-filter-row">${filters}</div>
      <div class="kv-sort-row"><span class="kv-sort-label">Sort</span>
        <button type="button" class="kv-sort active" data-kv-sort="featured">Featured</button>
        <button type="button" class="kv-sort" data-kv-sort="price-asc">Price: Low to High</button>
        <button type="button" class="kv-sort" data-kv-sort="price-desc">Price: High to Low</button>
        <button type="button" class="kv-sort" data-kv-sort="name">Name: A-Z</button>
        <button type="button" class="kv-sort" data-kv-sort="newest">Newest</button>
      </div>
    </div></section>`;
  }

  function findMasterProductList() {
    return document.querySelector('salla-products-list') || document.body;
  }

  function unwrapProducts(value, output = [], seen = new Set(), depth = 0) {
    if (depth > 8 || value == null) return output;
    if (typeof value !== 'object') return output;
    if (seen.has(value)) return output;
    seen.add(value);

    if (Array.isArray(value)) {
      const productLike = value.filter((item) => item && typeof item === 'object' && item.id && item.name && (
        item.price != null || item.sale_price != null || item.regular_price != null || item.thumbnail || item.image || item.urls?.customer || item.url
      ));
      if (productLike.length) output.push(...productLike);
      value.forEach((item) => unwrapProducts(item, output, seen, depth + 1));
      return output;
    }

    Object.values(value).forEach((child) => unwrapProducts(child, output, seen, depth + 1));
    return output;
  }

  function uniqueProducts(products) {
    const map = new Map();
    products.forEach((product) => {
      const id = Number(product?.id || 0);
      const name = normalize(product?.name || '');
      const key = id ? `id:${id}` : `name:${name}`;
      if (!name || map.has(key)) return;
      map.set(key, product);
    });
    return Array.from(map.values());
  }

  function productHref(product) {
    return String(product?.url || product?.urls?.customer || product?.links?.customer || '');
  }

  function productImage(product) {
    return String(product?.image?.url || product?.image || product?.thumbnail || product?.images?.[0]?.url || product?.images?.[0] || '');
  }

  function moneyValue(value) {
    if (value == null) return 0;
    if (typeof value === 'number') return value;
    if (typeof value === 'string') return Number(value.replace(/[^0-9.]/g, '')) || 0;
    if (typeof value === 'object') return Number(value.amount ?? value.value ?? value.price ?? 0) || 0;
    return 0;
  }

  function productPrice(product) {
    return moneyValue(product?.sale_price) || moneyValue(product?.price) || moneyValue(product?.regular_price) || moneyValue(product?.starting_price);
  }

  async function waitForSallaProductApi(timeout = 8000) {
    const started = Date.now();
    while (Date.now() - started < timeout) {
      if (window.salla?.product && typeof window.salla.product.fetch === 'function') return true;
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    return false;
  }

  async function callProductFetch(payload) {
    try {
      const response = await window.salla.product.fetch(payload);
      return uniqueProducts(unwrapProducts(response));
    } catch (error) {
      console.warn('KOTIVA V6 product.fetch failed', payload, error);
      return [];
    }
  }

  async function fetchCatalogFresh() {
    const ready = await waitForSallaProductApi();
    if (!ready) return [];

    // First use the documented storefront API directly. Unlike the web component,
    // this is not affected by Enaya's homepage section limit or render timing.
    let products = await callProductFetch({ source: 'latest', limit: EXPECTED_PRODUCTS });

    // Defensive fallbacks for stores where the backend caps the first page.
    if (products.length < EXPECTED_PRODUCTS) {
      const bigger = await callProductFetch({ source: 'latest', limit: 60 });
      products = uniqueProducts([...products, ...bigger]);
    }

    if (products.length < EXPECTED_PRODUCTS) {
      for (let page = 1; page <= 4 && products.length < EXPECTED_PRODUCTS; page += 1) {
        const batch = await callProductFetch({ source: 'latest', limit: 15, page });
        products = uniqueProducts([...products, ...batch]);
      }
    }

    // Keep only our KOTIVA catalog. If Salla still paginates/caps `latest`,
    // fill any missing products via the documented `search` source. This
    // removes the last dependency on homepage pagination limits.
    let matched = uniqueProducts(products.filter((product) => getMeta(product?.name || '')));

    if (matched.length < EXPECTED_PRODUCTS) {
      const have = new Set(matched.map((product) => normalize(product?.name)));
      const missing = PRODUCTS.filter((meta) => !have.has(normalize(meta.name)));

      for (let start = 0; start < missing.length; start += 5) {
        const batch = missing.slice(start, start + 5);
        const results = await Promise.all(batch.map(async (meta) => {
          const found = await callProductFetch({ source: 'search', source_value: meta.name, limit: 5 });
          return found.find((product) => normalize(product?.name) === normalize(meta.name)) ||
                 found.find((product) => normalize(product?.name).includes(normalize(meta.name))) || null;
        }));
        matched = uniqueProducts([...matched, ...results.filter(Boolean)]);
      }
    }

    matched.sort((a, b) => (getMeta(a.name)?.order || 999) - (getMeta(b.name)?.order || 999));

    if (matched.length) {
      try {
        sessionStorage.setItem(CATALOG_CACHE_KEY, JSON.stringify(matched));
      } catch (_) {}
    }
    return matched;
  }

  async function getCatalog(force = false) {
    if (!force && catalogProducts.length >= EXPECTED_PRODUCTS) return catalogProducts;
    if (!force) {
      try {
        const cached = JSON.parse(sessionStorage.getItem(CATALOG_CACHE_KEY) || '[]');
        if (Array.isArray(cached) && cached.length >= EXPECTED_PRODUCTS) {
          catalogProducts = cached;
          return catalogProducts;
        }
      } catch (_) {}
    }
    if (!catalogPromise || force) {
      catalogPromise = fetchCatalogFresh().then((items) => {
        catalogProducts = items;
        return items;
      }).finally(() => { catalogPromise = null; });
    }
    return catalogPromise;
  }

  function catalogCardHtml(product, { compact = false } = {}) {
    const meta = getMeta(product?.name || '');
    if (!meta) return '';
    const href = productHref(product);
    const localImage = `${MAIN_SITE}/${meta.image}`;
    const remoteImage = productImage(product);
    const src = localImage || remoteImage;
    const price = productPrice(product);
    const status = product?.status || (product?.is_out_of_stock ? 'out' : 'sale');
    const type = product?.type || 'product';
    const id = Number(product?.id || 0);
    const title = String(product?.name || meta.name).replace(/^Kotiva\s+/i, '');

    return `<article class="kv-v6-card ${compact ? 'kv-v6-card-compact' : ''}" data-product-id="${id}" data-name="${normalize(product?.name)}" data-filter="${meta.filter}" data-order="${meta.order}" data-featured="${meta.featured ? 1 : 0}" data-price="${price}" data-href="${href}">
      <a class="kv-v6-card-media" href="${href}" aria-label="Open ${title}">
        <img src="${src}" alt="Kotiva ${title}" loading="lazy" decoding="async" onerror="this.onerror=null;${remoteImage ? `this.src='${remoteImage}'` : ''}">
      </a>
      <div class="kv-v6-card-info">
        <a class="kv-v6-card-title" href="${href}">${title}</a>
        <div class="kv-v6-card-subtitle">${meta.category} · ${meta.skin}</div>
        <div class="kv-v6-card-price">SAR ${price.toFixed(2)} <span>EXCL. VAT</span></div>
        <div class="kv-v6-card-meta"><span class="kv-v6-pill">${meta.concern}</span><a class="kv-v6-discover" href="${href}">Discover →</a></div>
        ${id ? `<salla-add-product-button class="kv-v6-add" fill="outline" width="wide" product-id="${id}" product-status="${status}" product-type="${type}"><span>Add to cart</span></salla-add-product-button>` : ''}
      </div>
    </article>`;
  }

  function sortCatalog(items) {
    const result = [...items];
    if (activeSort === 'price-asc') return result.sort((a,b) => productPrice(a) - productPrice(b));
    if (activeSort === 'price-desc') return result.sort((a,b) => productPrice(b) - productPrice(a));
    if (activeSort === 'name') return result.sort((a,b) => normalize(a.name).localeCompare(normalize(b.name)));
    if (activeSort === 'newest') return result.sort((a,b) => (getMeta(b.name)?.order || 0) - (getMeta(a.name)?.order || 0));
    return result.sort((a,b) => {
      const ma = getMeta(a.name), mb = getMeta(b.name);
      const featured = Number(mb?.featured || false) - Number(ma?.featured || false);
      return featured || (ma?.order || 999) - (mb?.order || 999);
    });
  }

  function hideNativeListingSections() {
    document.querySelectorAll('salla-products-list').forEach((list) => {
      if (list.closest('#kv-similar-products-v6')) return;
      const section = list.closest('section') || list.parentElement;
      if (section) section.classList.add('kv-v6-native-list-hidden');
      else list.classList.add('kv-v6-native-list-hidden');
    });
  }

  function ensureListingShell(products) {
    document.querySelectorAll('#kv-shop-hero,#kv-shop-controls,#kv-v6-catalog').forEach((node) => node.remove());
    const footer = document.getElementById('kotiva-salla-footer');
    const anchor = footer || document.querySelector('footer') || document.body.lastElementChild;
    const wrapper = document.createElement('div');
    wrapper.id = 'kv-v6-catalog-shell';
    wrapper.innerHTML = `${createHero()}${createControls(products)}<section id="kv-v6-catalog"><div class="kv-v6-grid"></div><div class="kv-v6-empty" hidden>No products found in this category.</div><button id="kv-v6-load-more" type="button">Load more</button></section>`;
    if (anchor?.parentElement) anchor.parentElement.insertBefore(wrapper, anchor);
    else document.body.appendChild(wrapper);
  }

  function renderListing() {
    const grid = document.querySelector('#kv-v6-catalog .kv-v6-grid');
    const empty = document.querySelector('#kv-v6-catalog .kv-v6-empty');
    const loadMore = document.getElementById('kv-v6-load-more');
    if (!grid || !empty || !loadMore) return;

    const filtered = catalogProducts.filter((product) => {
      const meta = getMeta(product?.name || '');
      return meta && (activeFilter === 'all' || meta.filter === activeFilter);
    });
    const ordered = sortCatalog(filtered);
    const visible = ordered.slice(0, visibleLimit);
    grid.innerHTML = visible.map((product) => catalogCardHtml(product)).join('');
    empty.hidden = visible.length > 0;
    loadMore.hidden = visibleLimit >= ordered.length;
    loadMore.textContent = `Load more (${ordered.length - visibleLimit > 0 ? ordered.length - visibleLimit : 0})`;
    grid.querySelectorAll('salla-add-product-button').forEach((button) => styleNativeAddButton(button));
  }

  function bindListingEventsOnce() {
    if (document.body.dataset.kvV6ListingBound === '1') return;
    document.body.dataset.kvV6ListingBound = '1';
    document.addEventListener('click', (event) => {
      const filter = event.target.closest?.('[data-kv-filter]');
      if (filter) {
        event.preventDefault();
        activeFilter = filter.dataset.kvFilter || 'all';
        visibleLimit = INITIAL_VISIBLE;
        document.querySelectorAll('[data-kv-filter]').forEach((node) => node.classList.toggle('active', node === filter));
        renderListing();
        return;
      }
      const sort = event.target.closest?.('[data-kv-sort]');
      if (sort) {
        event.preventDefault();
        activeSort = sort.dataset.kvSort || 'featured';
        document.querySelectorAll('[data-kv-sort]').forEach((node) => node.classList.toggle('active', node === sort));
        renderListing();
        return;
      }
      const more = event.target.closest?.('#kv-v6-load-more');
      if (more) {
        event.preventDefault();
        visibleLimit += LOAD_MORE_STEP;
        renderListing();
        return;
      }
      const card = event.target.closest?.('.kv-v6-card');
      if (card && !event.target.closest?.('a,button,salla-add-product-button,input,select,label')) {
        const href = card.dataset.href;
        if (href) window.location.assign(href);
      }
    });
  }

  async function initListing() {
    if (cleanPath() !== ROOT_PATH || isProductPage() || listingPreparing) return;
    listingPreparing = true;
    document.body.classList.add('kv-catalog-loading');
    bindListingEventsOnce();
    const products = await getCatalog(true);
    if (!products.length) {
      console.error('KOTIVA V6: storefront catalog fetch returned no products; native listing left visible.');
      document.body.classList.remove('kv-catalog-loading');
      listingPreparing = false;
      return;
    }
    catalogProducts = products;
    hideNativeListingSections();
    ensureListingShell(products);
    renderListing();
    listingReady = true;
    listingPreparing = false;
    document.body.classList.remove('kv-catalog-loading');
    document.body.classList.add('kv-listing-ready');
    console.info(`KOTIVA V6 stable catalog: ${products.length} products loaded.`);
  }

  /* -------------------- PDP -------------------- */
  function closestCommonAncestor(a,b) {
    if (!a || !b) return null;
    const set = new Set();
    for (let n=a; n; n=n.parentElement) set.add(n);
    for (let n=b; n; n=n.parentElement) if (set.has(n)) return n;
    return null;
  }

  function findPdpParts() {
    const title = document.querySelector('[data-testid="store-product-title"]');
    const form = document.querySelector('[data-testid="store-product-form"]');
    const slider = document.querySelector('[data-testid="store-product-image-slider"],.details-slider');
    const gallery = slider?.closest('.sidebar') || slider?.parentElement || null;
    let info = title?.closest('.main-content') || (title && form ? closestCommonAncestor(title,form) : null) || title?.parentElement || null;
    if (info === document.body) info = title?.parentElement || null;
    const row = gallery && info ? closestCommonAncestor(gallery,info) : null;
    return {title,form,slider,gallery,info,row};
  }

  function findDescriptionBlock() {
    return document.querySelector('[data-testid="store-product-description"],.product__description');
  }

  function findOriginalDescriptionWrapper(desc) {
    let node = desc?.parentElement, depth=0;
    while (node && node !== document.body && depth++ < 5) {
      const text = (node.textContent || '').toLowerCase();
      if (text.includes('description') && text.includes('reviews')) return node;
      node = node.parentElement;
    }
    return desc?.parentElement || null;
  }

  function moveDescriptionIntoInfo(info,form) {
    if (!info || info.querySelector('.kv-pdp-editorial')) return;
    const desc = findDescriptionBlock();
    if (!desc) return;
    const wrapper = findOriginalDescriptionWrapper(desc);
    const editorial = document.createElement('div');
    editorial.className = 'kv-pdp-editorial';
    const titleText = document.querySelector('[data-testid="store-product-title"]')?.textContent || '';
    const parsed = parseDescription(desc);
    if (parsed && (parsed.intro || parsed.benefits.length || parsed.howTo)) {
      editorial.innerHTML = renderEditorial(parsed, getMeta(titleText));
      desc.classList.add('kv-pdp-hide');
      editorial.appendChild(desc); // keep original in DOM (hidden) for Salla scripts
    } else {
      editorial.appendChild(desc);
    }
    if (form) info.insertBefore(editorial,form); else info.appendChild(editorial);
    if (wrapper && wrapper !== desc && wrapper !== info && !wrapper.contains(info)) wrapper.classList.add('kv-pdp-description-origin-hidden');
  }

  /* Parse Salla's flat description into the Laravel PDP structure:
     intro paragraph, spec grid, Key Benefits, How to use, Key ingredients. */
  function parseDescription(desc) {
    const raw = (desc?.innerText || desc?.textContent || '').replace(/\r/g,'').replace(/[ \t]+/g,' ').trim();
    if (!raw) return null;
    const labels = ['Volume','Skin type','Concern','Benefits','How to use','Key ingredients'];
    // 'Volume:' style fields use a colon; 'Benefits' / 'How to use' / 'Key ingredients' are headings on their own line.
    const re = new RegExp(`(?:^|\\n)\\s*(${labels.join('|')})\\s*:?\\s*(?=\\S|$)`, 'ig');
    const hits = []; let m;
    while ((m = re.exec(raw))) hits.push({ label: m[1].toLowerCase(), index: m.index, end: m.index + m[0].length });
    const out = { intro: raw.slice(0, hits.length ? hits[0].index : raw.length), volume:'', skin:'', concern:'', benefits:[], howTo:'', ingredients:[] };
    const listOf = (t) => t.split(/\n+|•|·|(?<=[a-z\)])\s{2,}/).map((x) => x.replace(/^[-–•\s]+/,'').trim()).filter((x) => x.length > 2);
    hits.forEach((hit, i) => {
      const text = raw.slice(hit.end, hits[i+1] ? hits[i+1].index : raw.length).trim();
      if (hit.label === 'volume') out.volume = text.split('\n')[0].trim();
      else if (hit.label === 'skin type') out.skin = text.split('\n')[0].trim();
      else if (hit.label === 'concern') out.concern = text.split('\n')[0].trim();
      else if (hit.label === 'benefits') out.benefits = listOf(text);
      else if (hit.label === 'how to use') out.howTo = text.replace(/\s+/g,' ').trim();
      else if (hit.label === 'key ingredients') out.ingredients = listOf(text);
    });
    out.intro = out.intro.replace(/\s+/g,' ').trim();
    return out;
  }

  function renderEditorial(parsed, meta) {
    const esc = (v) => String(v || '').replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const spec = [
      ['Format', meta?.name ? meta.name.replace(/^kotiva\s+/i,'') : ''],
      ['Use on', meta?.relatedCategory === 'Hair' ? 'Hair' : meta?.relatedCategory === 'Body Care' ? 'Body' : 'Face'],
      ['Skin type', parsed.skin || meta?.skin || ''],
      ['Targets', parsed.concern || meta?.concern || ''],
      ['Size', parsed.volume || '']
    ].filter(([,v]) => v);
    return `
      ${parsed.intro ? `<p class="kv-pdp-intro">${esc(parsed.intro)}</p>` : ''}
      <div class="kv-pdp-spec">${spec.map(([k,v]) => `<div class="kv-pdp-spec-cell"><span class="kv-pdp-spec-k">${esc(k)}</span><span class="kv-pdp-spec-v">${esc(v)}</span></div>`).join('')}</div>
      ${parsed.benefits.length ? `<div class="kv-pdp-block"><h3 class="kv-pdp-h">Key benefits</h3><ul class="kv-pdp-benefits">${parsed.benefits.map((b) => `<li>${esc(b)}</li>`).join('')}</ul></div>` : ''}
      ${parsed.ingredients.length ? `<div class="kv-pdp-block"><h3 class="kv-pdp-h">Key ingredients</h3><div class="kv-pdp-ingredients">${parsed.ingredients.map((i) => `<span>${esc(i)}</span>`).join('')}</div></div>` : ''}
      ${parsed.howTo ? `<div class="kv-pdp-howto"><h3 class="kv-pdp-h">How to use</h3><p>${esc(parsed.howTo)}</p></div>` : ''}`;
  }

  function extractDescriptionField(label) {
    const desc = findDescriptionBlock();
    const text = (desc?.innerText || desc?.textContent || '').replace(/\s+/g,' ');
    const match = text.match(new RegExp(`${label}\\s*:\\s*([^|•\\n]+?)(?=\\s+(?:Volume|Skin type|Concern|Benefits|How to use|Key ingredients)\\s*:|$)`,'i'));
    return match ? match[1].trim() : '';
  }

  const PRICE_ONLY = /^\s*(?:SAR|ر\.س|﷼)?\s*\d[\d.,]*\s*(?:SAR|ر\.س|﷼)?\s*$/i;

  function hideNativePriceDisplays(info) {
    if (!info) return;
    info.querySelectorAll('*').forEach((el) => {
      if (el.closest('.kv-pdp-price-block,.kv-pdp-buy-row,.kv-pdp-editorial,salla-installment,[class*="tabby"],[id*="tabby"]')) return;
      if (el.children.length > 3) return;
      const text = (el.textContent || '').trim();
      if (!text) return;
      if (PRICE_ONLY.test(text) || /^(price|السعر)$/i.test(text)) {
        // climb to the smallest wrapper that only contains this price/label
        let target = el;
        while (target.parentElement && target.parentElement !== info && (target.parentElement.textContent || '').trim() === text) target = target.parentElement;
        target.classList.add('kv-pdp-hide');
      }
    });
  }

  function hideStrayDescriptions(info) {
    document.querySelectorAll('[data-testid="store-product-description"],.product__description,.product-description,.s-product-description,[data-testid="store-product-short-description"]').forEach((el) => {
      if (el.closest('.kv-pdp-editorial')) return;
      el.classList.add('kv-pdp-hide');
    });
    if (!info) return;
    // Enaya sometimes prints the description again as a short excerpt near the price.
    info.querySelectorAll('p,div,span').forEach((el) => {
      if (el.closest('.kv-pdp-editorial,.kv-pdp-price-block,.kv-pdp-buy-row')) return;
      const text = (el.textContent || '').replace(/\s+/g,' ').trim();
      if (text.length > 80 && /key ingredients|how to use|skin type/i.test(text) && el.children.length <= 2) el.classList.add('kv-pdp-hide');
    });
  }

  function collapseEmptyPdpSections() {
    const row = document.querySelector('.kv-pdp-main-row');
    const end = document.getElementById('kv-similar-products-v6') || document.getElementById('kotiva-salla-footer');
    if (!row) return;
    // Walk every ancestor level between the product row and the related section / footer
    // and hide siblings that render no visible content (these are the grey bands).
    let node = row;
    while (node && node !== document.body) {
      let sib = node.nextElementSibling;
      while (sib) {
        if (sib === end || sib.contains(end) || sib.id === 'kotiva-salla-footer' || sib.tagName === 'SCRIPT' || sib.tagName === 'STYLE') break;
        const text = (sib.innerText || sib.textContent || '').trim();
        const hasMedia = sib.querySelector('img,video,iframe,salla-products-slider:not(.kv-native-pdp-slider-hidden)');
        if (!text && !hasMedia) sib.classList.add('kv-pdp-empty-section');
        sib = sib.nextElementSibling;
      }
      node = node.parentElement;
    }
  }

  function injectPdpEditorialMeta(info,title) {
    if (!info || !title) return;
    const meta = getMeta(title.textContent || '');
    if (!meta) return;
    title.textContent = (title.textContent || '').trim();
    if (!info.querySelector('.kv-pdp-meta-head')) {
      const head = document.createElement('div');
      head.className = 'kv-pdp-meta-head';
      head.innerHTML = `<div class="kv-pdp-breadcrumb-lite"><span>Products</span><span class="kv-pdp-breadcrumb-sep">→</span><span>${meta.category}</span></div><div class="kv-pdp-skin">${meta.skin}</div>`;
      title.parentElement?.insertBefore(head,title);
    }
    if (!info.querySelector('.kv-pdp-category-line')) {
      const volume = extractDescriptionField('Volume');
      const line = document.createElement('div');
      line.className = 'kv-pdp-category-line';
      line.textContent = volume ? `${meta.category} · ${volume}` : meta.category;
      title.insertAdjacentElement('afterend',line);
    }
    const price = info.querySelector('[data-testid="store-product-price"],.s-product-price,salla-product-price,.product-price');
    if (price && !info.querySelector('.kv-pdp-price-block')) {
      const number = (price.textContent || '').replace(/,/g,'').match(/[\d.]+/);
      const base = number ? Number(number[0]) : null;
      const block = document.createElement('div');
      block.className = 'kv-pdp-price-block';
      block.innerHTML = base
        ? `<div class="kv-pdp-price">SAR ${base.toFixed(2)} <span>EXCL. VAT</span></div><small>SAR ${(base*1.15).toFixed(2)} including 15% VAT</small>`
        : `<div class="kv-pdp-price">${(price.textContent||'').trim()} <span>EXCL. VAT</span></div>`;
      price.insertAdjacentElement('beforebegin', block);
    }
    hideNativePriceDisplays(info);
    if (!info.querySelector('.kv-pdp-stock-concern')) {
      const block = document.createElement('div');
      block.className = 'kv-pdp-stock-concern';
      block.innerHTML = `<span class="kv-pdp-stock"><i></i> IN STOCK</span><span class="kv-pdp-concern">${meta.concern}</span>`;
      const editorial = info.querySelector('.kv-pdp-editorial');
      if (editorial) info.insertBefore(block,editorial); else title.insertAdjacentElement('afterend',block);
    }
  }

  function simplifyPdpPurchaseArea(info) {
    if (!info) return;
    const form = info.querySelector('[data-testid="store-product-form"]');
    const add = info.querySelector('[data-testid="store-product-add-to-cart"],salla-add-product-button');
    if (add) { add.removeAttribute('quick-buy'); add.removeAttribute('support-quick-buy'); styleNativeAddButton(add,'solid'); }

    if (form) {
      form.classList.add('kv-pdp-form');
      // One row: quantity stepper + Add to cart, exactly like the Laravel PDP.
      let row = form.querySelector('.kv-pdp-buy-row');
      if (!row) {
        row = document.createElement('div');
        row.className = 'kv-pdp-buy-row';
        const qty = form.querySelector('salla-quantity-input,[data-testid="store-product-quantity"],.s-quantity-input');
        if (qty) row.appendChild(qty);
        if (add) row.appendChild(add);
        form.appendChild(row);
      }
    }

    // Everything Salla adds for express checkout is hidden: by element name and by button text.
    const kill = (el) => { if (el && !el.closest('.kv-pdp-buy-row') && !el.matches('[data-testid="store-product-add-to-cart"]')) el.classList.add('kv-pdp-hide'); };
    info.querySelectorAll('salla-quick-order,salla-apple-pay,salla-quick-buy,apple-pay-button,[data-testid="store-product-quick-buy"],.s-quick-order,.s-apple-pay,.s-add-product-button-quick-buy,.quick-buy').forEach(kill);
    info.querySelectorAll('button,a,salla-button,div').forEach((el) => {
      if (el.children.length > 2) return;
      const t = (el.textContent || '').replace(/\s+/g,' ').trim().toLowerCase();
      if (/^(buy now|buy with|اشتر الآن|شراء سريع)/.test(t) || /apple\s*pay/.test(t)) kill(el.closest('salla-button,button,a') || el);
    });
    hideNativePriceDisplays(info);

    if (!info.querySelector('.kv-build-routine')) {
      const anchor = form || add;
      if (anchor) anchor.insertAdjacentHTML('afterend',`<a class="kv-build-routine" href="${MAIN_SITE}/routine-finder">Build Your Routine</a><div class="kv-cod-note">Cash on delivery available.</div><div class="kv-doctor-standard"><span>⚕</span><b>Doctor Approved · KOTIVA Standard</b><small>Formulated with clinically proven active ingredients</small></div>`);
    }
  }

  function hidePdpExtras() {
    document.querySelectorAll('[data-testid="store-product-social-share"],[data-testid="store-product-wishlist-btn"],.product-sku-wrapper')
      .forEach((el) => el.classList.add('kv-pdp-hide'));
    document.querySelectorAll('div,section').forEach((el) => {
      const text = (el.textContent || '').trim();
      if (text.includes('Secure payment') && text.includes('Fast shipping') && text.includes('Quality guarantee')) el.classList.add('kv-pdp-trust-strip');
    });
  }

  function currentProductId() {
    const pathMatch = location.pathname.match(/(?:-|\/)p(\d+)(?=\/|$)/i);
    const fromPath = Number(pathMatch?.[1] || 0);
    if (fromPath) return fromPath;
    const configValue = Number(window.salla?.config?.get?.('page.id') || 0);
    return configValue || null;
  }

  function hideAllNativeProductSliders() {
    document.querySelectorAll('salla-products-slider').forEach((slider) => {
      if (slider.closest('#kv-similar-products-v6')) return;
      const wrapper = slider.closest('section') || slider.closest('.container') || slider.parentElement || slider;
      wrapper.classList.add('kv-native-pdp-slider-hidden');
    });
    document.querySelectorAll('[data-testid="store-product-related"]').forEach((node) => {
      const wrapper = node.closest('section') || node.closest('.container') || node;
      wrapper.classList.add('kv-native-pdp-slider-hidden');
    });
    // Hide Enaya recommendation headings/wrappers that can remain after its slider is hidden.
    document.querySelectorAll('section,div').forEach((node) => {
      if (node.closest('#kv-similar-products-v6')) return;
      const text = (node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if ((text === 'complete your routine' || text === 'similar products' || text === 'you may also like') && node.querySelector('salla-products-slider')) {
        node.classList.add('kv-native-pdp-slider-hidden');
      }
    });
  }

  function removeExistingCustomSimilar() {
    document.getElementById('kv-similar-products-v6')?.remove();
    document.getElementById('kv-similar-products')?.remove();
  }

  async function buildSameCategorySimilarProducts() {
    hideAllNativeProductSliders();
    removeExistingCustomSimilar();

    const title = document.querySelector('[data-testid="store-product-title"]');
    const currentMeta = getMeta(title?.textContent || '');
    if (!currentMeta) return;

    const catalog = await getCatalog(false);
    if (!catalog.length) return;

    const currentId = currentProductId();
    const currentName = normalize(title?.textContent || '');
    const siblings = catalog.filter((product) => {
      const meta = getMeta(product?.name || '');
      if (!meta || meta.relatedCategory !== currentMeta.relatedCategory) return false;
      if (currentId && Number(product?.id) === Number(currentId)) return false;
      if (normalize(product?.name) === currentName) return false;
      return true;
    });

    // No siblings means absolutely no section and no reserved whitespace.
    if (!siblings.length) return;

    const section = document.createElement('section');
    section.id = 'kv-similar-products-v6';
    section.innerHTML = `<div class="kv-v6-similar-inner">
      <div class="kv-v6-similar-head"><p class="kv-v6-similar-eyebrow">You may also need</p><h2>Complete your<br>routine</h2><a class="kv-v6-similar-all" href="${ROOT_PATH}">View All →</a></div>
      <div class="kv-v6-similar-grid">${siblings.slice(0, 4).map((product) => catalogCardHtml(product)).join('')}</div></div>`;

    const footer = document.getElementById('kotiva-salla-footer') || document.querySelector('footer');
    if (footer?.parentElement) footer.parentElement.insertBefore(section, footer);
    else document.body.appendChild(section);

    section.querySelectorAll('salla-add-product-button').forEach((button) => styleNativeAddButton(button));
  }

  let pdpInitialized=false;
  let pdpObserver=null;
  function initPdp() {
    if (!isProductPage()) return false;
    const {title,form,gallery,info,row} = findPdpParts();
    if (!title || !gallery || !info) return false;
    document.documentElement.classList.add('kv-pdp');
    document.body.classList.add('kv-pdp-body');
    document.querySelectorAll('#kv-shop-hero,#kv-shop-controls,#kv-empty-filter,#kv-load-more-v5').forEach((el) => el.remove());
    gallery.classList.add('kv-pdp-gallery');
    info.classList.add('kv-pdp-info');
    row?.classList.add('kv-pdp-main-row');
    moveDescriptionIntoInfo(info,form);
    injectPdpEditorialMeta(info,title);
    simplifyPdpPurchaseArea(info);
    hideStrayDescriptions(info);
    hidePdpExtras();
    hideAllNativeProductSliders();
    collapseEmptyPdpSections();
    if (!pdpObserver) {
      let pending = null;
      pdpObserver = new MutationObserver(() => {
        if (pending) return;
        pending = setTimeout(() => {
          pending = null;
          simplifyPdpPurchaseArea(info);
          hideStrayDescriptions(info);
          hideNativePriceDisplays(info);
          hideAllNativeProductSliders();
          collapseEmptyPdpSections();
        }, 120);
      });
      pdpObserver.observe(info, { childList:true, subtree:true });
    }
    if (!pdpInitialized) {
      pdpInitialized=true;
      buildSameCategorySimilarProducts();
      setTimeout(hideAllNativeProductSliders,900);
      setTimeout(hideAllNativeProductSliders,1800);
    }
    return true;
  }

  function start() {
    if (isProductPage()) {
      let tries=0;
      const timer=setInterval(() => {
        tries += 1;
        if (initPdp() || tries >= 20) clearInterval(timer);
      },150);
      return;
    }
    if (cleanPath() === ROOT_PATH) {
      let tries=0;
      const timer=setInterval(() => {
        tries += 1;
        const list=findMasterProductList();
        if (list) { clearInterval(timer); initListing(); }
        else if (tries >= 30) clearInterval(timer);
      },150);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
  window.addEventListener('load',() => {
    if (isProductPage()) initPdp();
    else if (cleanPath() === ROOT_PATH && !listingReady) initListing();
  },{once:true});
})();