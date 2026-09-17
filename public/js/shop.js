/* ============================================================
   KOTIVA™ — Storefront commerce JS
   Add to cart, mini-cart drawer, quantity steppers, nav badge,
   toasts.

   House rules, matching js/layout.js:
     - IIFE + 'use strict', no module system, no dependencies.
     - ES2017: fetch and Promises are used, async/await is not,
       to stay in the same idiom as layout.js.
     - PROGRESSIVE. Every control this file enhances is a real
       <form> that already works on its own. Nothing here is
       load-bearing: with JS off the same actions POST and
       redirect, which is why each handler starts from a form
       and calls preventDefault() rather than from a bare button.
     - The server is the only thing that does cart arithmetic.
       Every mutation response carries the recomputed totals and
       this file renders them; it never adds prices up itself.
   ============================================================ */

(function () {
  'use strict';

  var SELECTORS = {
    drawer: '.mini-cart',
    drawerPanel: '.mini-cart-panel',
    drawerBody: '[data-mini-cart-items]',
    drawerSubtotal: '[data-mini-cart-subtotal]',
    drawerEmpty: '[data-mini-cart-empty]',
    badge: '[data-cart-badge]'
  };

  /* ── plumbing ───────────────────────────────────────────── */

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function request(url, method, body) {
    return fetch(url, {
      method: method,
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (payload) {
        if (!res.ok) {
          var err = new Error(payload.message || 'Something went wrong.');
          err.status = res.status;
          err.payload = payload;
          throw err;
        }
        return payload;
      });
    });
  }

  function money(currency, amount) {
    return currency + ' ' + amount;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ── toast ──────────────────────────────────────────────── */

  var toastTimer = null;

  function toast(message, kind) {
    var host = document.querySelector('.kv-toast');

    if (!host) {
      host = document.createElement('div');
      host.className = 'kv-toast';
      /* status, not alert: an added-to-cart confirmation should not
         interrupt a screen reader mid-sentence. Errors get alert. */
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }

    host.textContent = message;
    host.classList.toggle('is-error', kind === 'error');
    host.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    host.classList.add('is-visible');

    if (toastTimer) { clearTimeout(toastTimer); }
    toastTimer = setTimeout(function () { host.classList.remove('is-visible'); }, 4000);
  }

  /* ── nav badge ──────────────────────────────────────────── */

  function paintBadge(count) {
    var badges = document.querySelectorAll(SELECTORS.badge);
    [].forEach.call(badges, function (badge) {
      badge.textContent = count > 0 ? String(count) : '';
      badge.classList.toggle('is-empty', count <= 0);
      badge.setAttribute('aria-label', count === 1 ? '1 item in cart' : count + ' items in cart');
    });
  }

  /* ── mini-cart drawer ───────────────────────────────────── */

  var lastFocused = null;

  function drawer() { return document.querySelector(SELECTORS.drawer); }

  function focusable(root) {
    return [].slice.call(root.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])'))
      .filter(function (el) { return el.offsetParent !== null; });
  }

  function openDrawer() {
    var el = drawer();
    if (!el) { return; }

    lastFocused = document.activeElement;
    el.classList.add('open');
    el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    var targets = focusable(el);
    if (targets.length) { targets[0].focus(); }
  }

  function closeDrawer() {
    var el = drawer();
    if (!el || !el.classList.contains('open')) { return; }

    el.classList.remove('open');
    el.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    /* Return focus where it came from, the way initHamburger does. */
    if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
    lastFocused = null;
  }

  function renderDrawer(payload) {
    var el = drawer();
    if (!el) { return; }

    var body = el.querySelector(SELECTORS.drawerBody);
    var subtotal = el.querySelector(SELECTORS.drawerSubtotal);
    var empty = el.querySelector(SELECTORS.drawerEmpty);
    var items = payload.items || [];

    if (body) {
      body.innerHTML = items.map(function (item) {
        return '' +
          '<li class="mini-cart-item">' +
            '<a class="mini-cart-item-img" href="' + escapeHtml(item.url) + '">' +
              '<img src="' + escapeHtml(item.image) + '" alt="" loading="lazy" />' +
            '</a>' +
            '<div class="mini-cart-item-body">' +
              '<a class="mini-cart-item-name" href="' + escapeHtml(item.url) + '">' + escapeHtml(item.name) + '</a>' +
              '<div class="mini-cart-item-meta">' + item.qty + ' &times; ' + escapeHtml(money(payload.currency, item.unit_price)) + '</div>' +
            '</div>' +
            '<div class="mini-cart-item-total">' + escapeHtml(money(payload.currency, item.line_total)) + '</div>' +
          '</li>';
      }).join('');
    }

    if (subtotal) { subtotal.textContent = money(payload.currency, payload.subtotal); }
    if (empty) { empty.hidden = items.length > 0; }
    el.classList.toggle('is-empty', items.length === 0);
  }

  function applyPayload(payload) {
    paintBadge(payload.count || 0);
    renderDrawer(payload);
  }

  /* ── add to cart ────────────────────────────────────────── */

  function bindAddToCart() {
    document.addEventListener('submit', function (e) {
      var form = e.target.closest('[data-add-to-cart]');
      if (!form) { return; }

      e.preventDefault();

      var button = form.querySelector('[type="submit"]');
      var original = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = 'Adding…'; }

      var data = new FormData(form);

      request(form.getAttribute('action'), 'POST', {
        product_id: Number(data.get('product_id')),
        qty: Number(data.get('qty') || 1)
      }).then(function (payload) {
        applyPayload(payload);
        openDrawer();
        if (button) { button.textContent = 'Added ✓'; }
        setTimeout(function () {
          if (button) { button.disabled = false; button.textContent = original; }
        }, 1400);
      }).catch(function (err) {
        toast(err.message, 'error');
        /* 422 carries the true maximum; show it on the control itself
           rather than only in a toast that scrolls away. */
        var hint = form.querySelector('[data-stock-hint]');
        if (hint && err.payload && typeof err.payload.available === 'number') {
          hint.textContent = err.payload.available > 0
            ? 'Only ' + err.payload.available + ' left'
            : 'Sold out';
          hint.hidden = false;
        }
        if (button) { button.disabled = false; button.textContent = original; }
      });
    });
  }

  /* ── cart page ──────────────────────────────────────────── */

  function bindCartPage() {
    document.addEventListener('submit', function (e) {
      var updateForm = e.target.closest('[data-cart-update]');
      var removeForm = e.target.closest('[data-cart-remove]');
      var form = updateForm || removeForm;
      if (!form) { return; }

      e.preventDefault();

      var row = form.closest('.cart-item');
      var method = updateForm ? 'PATCH' : 'DELETE';
      var body = null;

      if (updateForm) {
        var input = form.querySelector('input[name="qty"]');
        body = { qty: Number(input ? input.value : 0) };
      }

      /* Optimistic: dim the row immediately so the click feels answered,
         and restore it verbatim if the server refuses. */
      if (row) { row.classList.add('is-busy'); }

      request(form.getAttribute('action'), method, body)
        .then(function (payload) {
          applyPayload(payload);
          /* Totals are the server's, so re-render the page rather than
             patching numbers in place and risking a divergent view. */
          window.location.reload();
        })
        .catch(function (err) {
          if (row) { row.classList.remove('is-busy'); }
          toast(err.message, 'error');
        });
    });
  }

  /* ── init ───────────────────────────────────────────────── */

  function bindDrawerControls() {
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-mini-cart-open]')) {
        e.preventDefault();
        refresh().then(openDrawer);
        return;
      }
      if (e.target.closest('[data-mini-cart-close]') || e.target.closest('.mini-cart-scrim')) {
        closeDrawer();
      }
    });

    document.addEventListener('keydown', function (e) {
      var el = drawer();
      if (!el || !el.classList.contains('open')) { return; }

      if (e.key === 'Escape') { closeDrawer(); return; }

      if (e.key === 'Tab') {
        var targets = focusable(el);
        if (!targets.length) { return; }
        var first = targets[0];
        var last = targets[targets.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  function refresh() {
    return fetch('/cart/summary', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) { return res.json(); })
      .then(function (payload) { applyPayload(payload); return payload; })
      .catch(function () { /* a missing badge is not worth an error */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindAddToCart();
    bindCartPage();
    bindDrawerControls();
    refresh();
  });
})();

/* ============================================================
   KOTIVA™ — Checkout enhancements
   Zone → city narrowing, live shipping fee, and submit guards.

   Separate IIFE so the cart code above stays self-contained.
   Everything here is enhancement only: the shipping form already
   renders every city grouped by area and the server recalculates
   the fee on submit, so a visitor with JS off completes checkout
   through exactly the same endpoints.
   ============================================================ */

(function () {
  'use strict';

  /* ── zone → city narrowing + live fee ───────────────────── */

  function initShippingStep() {
    var form = document.querySelector('[data-checkout-shipping]');
    if (!form) { return; }

    var zoneSelect = form.querySelector('[data-shipping-zone]');
    var citySelect = form.querySelector('[data-shipping-city]');
    var feeOut = document.querySelector('[data-shipping-fee]');
    var estimateOut = document.querySelector('[data-shipping-estimate]');
    var subtotalInput = form.querySelector('input[name="subtotal"]');
    if (!zoneSelect || !citySelect) { return; }

    /* The full list is in the DOM already; narrowing hides the groups that do
       not belong to the chosen area rather than refetching them. Hidden
       optgroups are also DISABLED, because `hidden` alone still leaves an
       option selectable by keyboard in some engines. */
    function narrowCities() {
      var zoneId = zoneSelect.value;
      var groups = citySelect.querySelectorAll('optgroup');
      var anyVisible = false;

      [].forEach.call(groups, function (group) {
        var matches = !zoneId || group.getAttribute('data-zone') === zoneId;
        group.hidden = !matches;
        group.disabled = !matches;
        if (matches) { anyVisible = true; }
      });

      /* If the selected city belongs to a hidden group, clear it so the form
         cannot submit a city from another area. */
      var selected = citySelect.options[citySelect.selectedIndex];
      if (selected && selected.getAttribute('data-zone') && selected.getAttribute('data-zone') !== zoneId) {
        citySelect.value = '';
      }

      return anyVisible;
    }

    function refreshQuote() {
      var zoneId = zoneSelect.value;

      if (!zoneId) {
        if (feeOut) { feeOut.textContent = 'Select a delivery area'; }
        if (estimateOut) { estimateOut.hidden = true; }
        return;
      }

      var subtotal = subtotalInput ? subtotalInput.value : '0.00';
      var url = '/api/shipping/cities?zone_id=' + encodeURIComponent(zoneId) +
                '&subtotal=' + encodeURIComponent(subtotal);

      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
          var s = payload && payload.shipping;
          if (!s) { return; }

          if (feeOut) { feeOut.textContent = s.fee_label; }

          if (estimateOut) {
            var parts = [];
            if (s.estimate) { parts.push('Estimated delivery: ' + s.estimate + '.'); }
            if (s.amount_to_free_shipping) {
              parts.push('Spend ' + s.amount_to_free_shipping + ' more for free delivery.');
            }
            estimateOut.textContent = parts.join(' ');
            estimateOut.hidden = parts.length === 0;
          }
        })
        .catch(function () {
          /* The server recalculates on submit, so a failed quote costs the
             shopper a preview, never the correct fee. */
        });
    }

    zoneSelect.addEventListener('change', function () {
      narrowCities();
      refreshQuote();
    });

    narrowCities();
    refreshQuote();
  }

  /* ── submit guards ──────────────────────────────────────── */

  function guard(selector, busyLabel) {
    var form = document.querySelector(selector);
    if (!form) { return; }

    var submitted = false;

    form.addEventListener('submit', function (e) {
      /* Placing an order writes stock and sends mail — a double submit is the
         one thing this form must never do. The server also refuses a spent
         checkout token, so this is the first of two defences, not the only. */
      if (submitted) {
        e.preventDefault();
        return;
      }

      submitted = true;

      var button = form.querySelector('[type="submit"]');
      if (button) {
        button.disabled = true;
        button.dataset.originalLabel = button.textContent;
        button.textContent = busyLabel;
      }

      /* A disabled button is not submitted with the form, and this one carries
         no value, so nothing is lost by disabling it here. If the browser
         restores the page from bfcache, re-enable so the form is usable. */
      window.addEventListener('pageshow', function (evt) {
        if (!evt.persisted) { return; }
        submitted = false;
        if (button) {
          button.disabled = false;
          button.textContent = button.dataset.originalLabel || button.textContent;
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initShippingStep();
    guard('[data-checkout-shipping]', 'Saving…');
    guard('[data-checkout-place]', 'Placing order…');
  });
})();
