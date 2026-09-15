# KOTIVA — E-commerce Implementation Brief

You are working inside the `Kotiva` repository (branch `feat/shop-v1`). Read this brief fully before touching any file. Then read the repo itself (`README.md`, `js/layout.js`, `js/data-lite.js`, `js/data-full.js`, `css/kotiva.css`, `shop.html`, `product.html`, `scripts/generate-product-pages.js`) so you understand the existing design system and data before you write code.

---

## 1. Context

KOTIVA is a premium skincare brand (25 SKUs, prices in SAR, VAT-inclusive). The repo is a **hand-built static site**: vanilla HTML/CSS/JS, no framework, no backend. It was delivered by a previous agency and is production-quality: strong design system, three-theme CSS, light/dark mode, a Routine Finder quiz engine, pre-rendered product pages for SEO, and consistency-check scripts.

The client now wants the site turned into a **real e-commerce store** with an **admin dashboard**, under two hard constraints:

1. **Same source code.** No migration of the frontend to React/Next/Vue or any SPA framework. The existing HTML, CSS and vanilla JS stay and are extended.
2. **Same visual identity.** New pages must look like they were designed by the same people who designed the current site.

The agreed technical direction: **Laravel (latest stable) + MySQL** for the backend, **Blade** for server-rendered pages reusing the existing markup, **Filament** for the admin dashboard. Payment is **Cash on Delivery only** for now; online payment (Tabby / Moyasar / similar, Saudi market) comes later and must be trivial to plug in.

---

## 2. Non-negotiable rules

- Do not change the look, spacing, typography, colours, animations or behaviour of existing pages except where this brief requires it. When you port a page to Blade, the rendered HTML must be functionally identical to the original.
- Reuse `css/kotiva.css`, `js/layout.js`, `js/animations.js`, the fonts and all assets as they are. Add new CSS in a **new file** (`public/css/shop.css`) using the existing design tokens (`--font-display`, `--bronze`, `--border`, `--bg-card`, `--nav-h`, `--radius-pill`, etc.) and the existing component classes (`.btn`, `.btn-primary`, `.filter-pill`, `.product-card`, `.pdp-*`, `.accordion-*`). Do not introduce Tailwind, Bootstrap or any CSS framework on the storefront.
- New frontend JavaScript is **vanilla ES2017-compatible**, in a new file (`public/js/shop.js`), following the IIFE + `'use strict'` style of `layout.js`. No jQuery, no bundler on the storefront. Alpine.js is acceptable **only** inside Filament (it ships with it).
- Every page must work in both light and dark mode (`html[data-mode="dark"]`) and on mobile (≤ 768px). Check the existing dark-mode selectors in `kotiva.css` and mirror them for new components.
- No placeholder code, no `TODO` left behind, no stubbed methods, no fake data in production paths. If something is out of scope, leave it out cleanly rather than half-implemented.
- **Zero syntax errors and zero logical errors.** You must actually run the application, run the tests, and click through every flow before you report a phase as done. See §12.

---

## 3. Target repository layout

Convert the repo root into a Laravel application. Keep git history; move files with `git mv`.

```
app/                      Laravel app (Models, Http, Services, Filament, Mail, Jobs)
bootstrap/  config/  routes/  database/  resources/  tests/  storage/
public/
  index.php
  css/kotiva.css          moved from css/ (unchanged)
  css/shop.css            NEW — storefront e-commerce styles
  js/layout.js            moved (unchanged)
  js/animations.js        moved (unchanged)
  js/routine-engine.js    moved (unchanged)
  js/routine-model.js     moved (unchanged)
  js/shop.js              NEW — cart / checkout / listing behaviour
  fonts/  assets/  img/   moved (unchanged)
  robots.txt  llms.txt    moved
resources/views/
  layouts/app.blade.php   ONE shared header/nav/mobile-menu/footer for all pages
  pages/                  home, about, science, journal, contact, ingredients,
                          routine-finder, privacy-policy, terms (ported 1:1)
  shop/                   index, show
  cart/                   index
  checkout/               review, shipping, payment, confirmation
  emails/                 order-placed (customer), order-placed-admin, order-status
  components/             product-card, price, cart-badge, breadcrumb, stepper …
legacy/                   ORIGINAL static files kept for reference only, excluded from
                          the web root (about.html, product/*.html, scripts/, data/ …)
```

Rules for the port:

- Extract the header, mobile overlay and footer **once** into `layouts/app.blade.php`. The previous agency maintained them duplicated across 36 files with a consistency script; that script (`scripts/check-global-consistency.js`) is now unnecessary — the layout is the single source. Delete `product/*.html`, `scripts/generate-*.js`, `scripts/check-*.js`, `debug-safari.js`, `verify-safari-fix.js`, the root `package.json` Playwright dependency, and the two pre-rendered product/ingredients generators. Move `data/routine-model.json` and `scripts/test-routine-engine.mjs` under `resources/routine/` and keep the routine engine test runnable via `npm run test:routine`.
- Keep `js/layout.js`'s current-nav detection working. It derives the active link from `location.pathname` and expects paths like `/shop`, `/product/<slug>`, `/ingredients`. Define your routes so those patterns still match (see §5) and update the two regexes in `markCurrentNav()` only if a route genuinely differs. Test this on every page.
- The contact and newsletter forms currently POST to an external n8n webhook. Replace with Laravel routes (`POST /contact`, `POST /newsletter`) that validate, store (`contact_messages`, `newsletter_subscribers` tables) and send a notification email to the admin address from config. Keep the existing front-end success/error UI.
- Cache-busting: replace every `?v=xxx` asset reference with Laravel's `asset()` plus a single version string from `config('app.asset_version')`, or use Vite with `@vite` **only** to copy/version the static files (no transpiling of the storefront JS).
- SEO parity: every product page must render server-side the same `<title>`, meta description, canonical, Open Graph tags and `Product` JSON-LD that the old generator produced (see `legacy/scripts/generate-product-pages.js` for the exact shape). JSON-LD `availability` must reflect real stock (`InStock` / `OutOfStock`). Regenerate `sitemap.xml` dynamically from the DB (`/sitemap.xml` route).

---

## 4. Database schema

Use Laravel migrations. MySQL 8, `utf8mb4`. Money is stored as `DECIMAL(10,2)` in SAR. Every table has `created_at`/`updated_at`; soft-delete where noted.

**categories** — `id, name, slug (unique), description, sort_order, is_active`
**products** (soft deletes) — `id, sku (unique, e.g. KOT001), slug (unique), name, category_id (FK), skin_type, concern, action, volume, price, compare_at_price (nullable), description (text), benefits (json array), how_to_use (text), science (longtext), ingredients (json array), free_from (json array), filter_tags (json array), image (path), gallery (json array of paths), is_featured, is_best_seller, is_active, stock_qty (unsigned int), low_stock_threshold (int, default 5), meta_title, meta_description, weight_grams (nullable)`
**product_stock_movements** — `id, product_id, delta (signed int), reason enum(manual, import, order_reserved, order_released, order_fulfilled, restock), reference_type, reference_id, note, admin_id (nullable)` — every stock change must be recorded here; `products.stock_qty` is the cached current value.
**shipping_zones** — `id, name, is_active, sort_order` (e.g. "Riyadh", "Eastern Province", "Rest of KSA")
**shipping_cities** — `id, zone_id (FK), name_en, name_ar, is_active`
**shipping_rates** — `id, zone_id (FK), fee, free_shipping_threshold (nullable), estimated_days_min, estimated_days_max, is_active`
**carts** — `id, token (uuid, unique), customer_email (nullable), expires_at, last_activity_at`
**cart_items** — `id, cart_id (FK), product_id (FK), qty, unit_price_snapshot` (unique on cart_id+product_id)
**customers** — `id, first_name, last_name, email (unique), phone, marketing_opt_in` (guest checkout only; no customer login in v1, but this table lets the dashboard show repeat buyers)
**orders** — `id, order_no (unique, e.g. KOT-7F3K2Q), customer_id (FK), status enum(pending, confirmed, processing, shipped, delivered, cancelled, refunded), payment_method enum(cod), payment_status enum(unpaid, paid, refunded), currency ('SAR'), subtotal, shipping_fee, discount_total, vat_amount (informational — prices are VAT-inclusive; compute 15% VAT portion for display), grand_total, shipping_zone_id, shipping_city_id, shipping_name, shipping_phone, shipping_address_line1, shipping_address_line2, shipping_district, shipping_city_name (snapshot), shipping_postal_code, customer_note, admin_note, placed_at, confirmed_at, shipped_at, delivered_at, cancelled_at, ip_address, user_agent`
**order_items** — `id, order_id (FK), product_id (FK nullable on delete set null), sku_snapshot, name_snapshot, image_snapshot, unit_price, qty, line_total`
**order_status_histories** — `id, order_id, from_status, to_status, admin_id (nullable), note`
**settings** — key/value (json) table for: store email, admin notification emails, COD enabled, cart TTL hours, low-stock alert email, announcement bar text, VAT rate.
**contact_messages**, **newsletter_subscribers**, **admins** (Filament users, with roles: `super_admin`, `manager`, `staff` via `spatie/laravel-permission` or Filament Shield).

**Seeding.** Write `ProductSeeder` that reads `legacy/js/data-lite.js` and `legacy/js/data-full.js` (parse the JS array — a regex extracting the `[...]` literal and `json_decode` is fine, they are valid JSON literals) and inserts the 25 products with all fields, categories derived from `category`, and an initial `stock_qty` of 50 each recorded as a `restock` movement. Seed 3 shipping zones with realistic KSA cities (Riyadh, Jeddah, Dammam, Khobar, Makkah, Madinah, Taif, Abha, Tabuk, Buraidah, Hail, Jizan, Najran, Al Ahsa, Jubail, Yanbu) and fees (e.g. 25 / 30 / 40 SAR, free over 300 SAR). Seed one super-admin from `.env` values. `php artisan migrate:fresh --seed` must produce a fully working store.

---

## 5. Routes (storefront)

```
GET  /                         home
GET  /shop                     listing
GET  /product/{slug}           product page (301 from /product.html?id=N and /product/{slug}.html)
GET  /about /science /journal /contact /ingredients /routine-finder
GET  /privacy-policy /terms
POST /contact  POST /newsletter

GET  /cart                     cart page
POST /cart/items               add   {product_id, qty}
PATCH /cart/items/{item}       update qty
DELETE /cart/items/{item}      remove
GET  /cart/summary             JSON (count, subtotal) for the nav badge

GET  /checkout                 → redirect to /checkout/review
GET  /checkout/review
GET  /checkout/shipping   POST /checkout/shipping
GET  /checkout/payment    POST /checkout/payment      (places the order)
GET  /checkout/confirmation/{order_no}   (signed URL or session-gated)

GET  /api/products             JSON used by the Routine Finder (id, slug, name, price, image, filter_tags, stock)
GET  /api/shipping/cities?zone_id=   JSON
GET  /sitemap.xml
```

Add 301 redirects for every old static URL (`/shop.html` → `/shop`, `/about.html` → `/about`, etc.) so existing indexed links keep working.

---

## 6. Storefront features

### 6.1 Listing page (`/shop`)
- Same hero, same filter pills, same 3-column grid and `.product-card` markup as today, now rendered from the DB (only `is_active` products).
- Filter pills: keep current behaviour (client-side via `data-tags`), driven from `filter_tags`; counts computed server-side.
- Add a **sort control** matching the pill style: Featured (default: `is_featured` desc, `sort_order`), Price low→high, Price high→low, Name A→Z, Newest. Sort is a query param (`?sort=price_asc`) handled server-side; filter pill state persists through sort changes.
- Product cards show price, "VAT incl." and a compact **Add to cart** button in addition to the existing "Discover →" link. Out-of-stock products show a "Sold out" state (muted card, button disabled), still visible in the list.
- Keep the `?routine=slug,slug` handoff and `?filter=` deep link working (see `initRoutineFilter` in `layout.js`).

### 6.2 Product page (`/product/{slug}`)
- Port the existing PDP layout 1:1 (`.pdp-*` classes, benefits list, how-to-use box, ingredients pills, structured science section, doctor badge, related products rail).
- Replace "Where to Buy" with: price block, quantity stepper (1 … min(10, stock)), **Add to cart** button, stock indicator ("In stock" / "Only N left" when ≤ `low_stock_threshold` / "Sold out"), and a small note "Cash on delivery available".
- Adding to cart opens a slide-in **mini-cart drawer** (new component, styled on the existing tokens) showing items, subtotal and buttons "View cart" / "Checkout". Update the nav cart badge without a page reload.
- Related products: same category, active, in-stock first, max 4.

### 6.3 Cart (`/cart`)
- Table/list of items with image, name, unit price, quantity stepper, line total, remove. Order summary card: subtotal, "Shipping calculated at checkout", VAT note, total. "Continue shopping" and "Proceed to checkout".
- Empty state with a link back to the shop.
- All quantity changes go through the API endpoints and re-render totals from the server response — never trust client-side math.

### 6.4 Cart persistence & lifecycle
- Cart is identified by a `kotiva_cart` **httpOnly cookie** holding the cart `token` (UUID). Server-side `carts` row. No `localStorage` for the cart itself.
- TTL: `settings.cart_ttl_hours` (default 72). `last_activity_at` refreshes on every cart mutation; `expires_at = last_activity_at + TTL`. A scheduled command `carts:purge` (hourly) deletes expired carts and their items and **releases any reserved stock** (see 6.5). Also lazily expire on access: if the cookie points to an expired cart, treat it as empty and issue a fresh one.
- Cart items snapshot the unit price at add time but **the checkout always re-prices from the current product price** and tells the user if a price changed.

### 6.5 Stock handling (must be correct under concurrency)
- Adding to cart **reserves** stock: within a DB transaction, `SELECT … FOR UPDATE` the product, check `stock_qty >= requested`, decrement `stock_qty`, write a `stock_movement` with reason `order_reserved`, reference = cart item. Updating qty adjusts the reservation by the delta; removing an item or purging an expired cart **releases** it (`order_released`).
- Placing an order converts the reservation into a fulfilment record (`order_fulfilled` movement referencing the order); cancelling an order (from the dashboard) restores stock (`order_released`).
- Never let `stock_qty` go negative. If a request would, return a `422` with a clear message and the maximum available quantity, and the UI must show it inline.
- Checkout payment step re-validates every line against current stock and active status before creating the order, inside one transaction with the order insert.

### 6.6 Checkout (three steps + confirmation)
Use a shared stepper component (Review → Shipping → Payment) with the current step highlighted, in the site's typographic style. Each step is its own route; server-side session holds the progress; visiting a later step without completing the earlier one redirects back.

- **Review**: read-only summary of the cart; edit link back to `/cart`; price-changed / stock-changed warnings if any.
- **Shipping**: form — first name, last name, email, phone (KSA format validation: `^(\+966|0)?5\d{8}$`, normalised to `+9665XXXXXXXX`), zone (select), city (select, populated from `/api/shipping/cities` when the zone changes, with the server-side list as fallback), address line 1, address line 2 (optional), district, postal code (optional), order note (optional), marketing opt-in checkbox. Shipping fee and estimated delivery are shown live as soon as a zone is chosen; free-shipping threshold applied. Validate with a FormRequest; errors render inline in the existing form style used on `contact.html`.
- **Payment**: single option "Cash on Delivery" (radio, pre-selected). Full order summary including shipping fee and grand total. Terms checkbox linking to `/terms`. "Place order" button, disabled while submitting, protected against double submit (idempotency: a `checkout_token` in session invalidated after the first successful order).
- **Confirmation**: thank-you page with order number, delivery estimate, itemised summary, shipping address, and "Continue shopping". Accessible only via signed URL or the placing session (do not let anyone enumerate orders).
- The payment step must be built so that adding an online gateway later means: add a `payment_method` enum value, add a `PaymentGateway` interface implementation, and a `PaymentController@callback`. Create the interface now with a `CashOnDeliveryGateway` implementation. Do not build any gateway integration.

### 6.7 Order numbers
Format `KOT-XXXXXX`: 6 characters from the alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no 0/O/1/I), generated with `random_int`, unique index in DB, retry on collision (max 5 attempts, then throw). Implemented in `App\Support\OrderNumber`. Cover with a unit test.

### 6.8 Emails
Use Laravel Mailables with **Markdown or Blade HTML templates styled to match the brand** (logo, Futura-style headings via system fallback, bronze accent). Queue them (`ShouldQueue`, database queue driver by default).
- `OrderPlacedCustomer` — to the customer: order no., items, totals, shipping address, COD note, delivery estimate, contact link.
- `OrderPlacedAdmin` — to `settings.admin_notification_emails`: same plus link to the order in the dashboard.
- `OrderStatusUpdated` — to the customer on `shipped`, `delivered`, `cancelled`.
- `LowStockAlert` — to admin when a product crosses `low_stock_threshold` after any decrement (debounce: once per product per 24 h).
- `ContactMessageReceived`, `NewsletterWelcome`.
Provide a `mail.log` default so local dev works without SMTP, and document SMTP env vars in `.env.example`.

---

## 7. Admin dashboard (Filament)

Install Filament v3 at `/admin`. Brand it (logo, bronze primary colour, dark mode enabled). Protect with auth + roles (`super_admin` full access, `manager` no settings/admins, `staff` orders read/update only).

### 7.1 Dashboard home
Widgets: revenue today / 7 days / 30 days (delivered + confirmed orders), orders count by status, average order value, a line chart of revenue and orders for the last 30 days, a bar chart of top 10 products by quantity sold, low-stock products table, latest 10 orders table. Date-range filter on the charts.

### 7.2 Catalog
- **Categories** resource: CRUD, reorderable, active toggle, product count column.
- **Products** resource: CRUD with tabs (General, Content, Media, Inventory, SEO). Rich text/markdown for `description`/`science`; repeaters for `benefits`, `ingredients`, `free_from`, `filter_tags`; image upload with preview (stores to `public/storage/products`, generates a 1200px WebP and a 600px thumbnail); gallery upload; toggles for featured/best-seller/active; SKU and slug auto-generated but editable. Table: image, SKU, name, category, price, stock (colour-coded), status; filters by category/status/stock level; bulk actions activate/deactivate.
- **Stock**: on the product edit page, a "Stock" tab showing current qty, a form to add/remove stock with a reason and note (creates a movement, adjusts qty in a transaction), and a table of movements. A separate **Stock Movements** resource (read-only, filterable by product/reason/date).
- **Import products from Excel/CSV**: a Filament action on the Products list that accepts `.xlsx`/`.csv` (use `maatwebsite/excel` or `openspout`), shows a preview of the first 10 parsed rows with column mapping, validates every row (required: sku, name, price, category; optional: everything else, arrays as `|`-separated), reports row-level errors without importing anything if any row fails (unless "skip invalid rows" is ticked), and upserts by SKU. Provide a downloadable template file. Run the import as a queued job with progress notification for > 100 rows.
- **Export** products and orders to CSV.

### 7.3 Shipping
- **Zones** resource: CRUD, active toggle, sort order.
- **Cities** resource: CRUD, belongs to zone, EN/AR names, active toggle, bulk import from CSV.
- **Rates**: managed on the zone page (fee, free-shipping threshold, ETA days). Changing a rate never alters existing orders (they store snapshots).

### 7.4 Orders
- Table: order no., customer, city, items count, grand total, payment status, status (badge), placed at. Filters: status, date range, city/zone, payment status. Global search by order no., email, phone.
- View page: full order, items, address, status timeline, admin notes, customer's previous orders count. Actions: change status (with note; enforces the allowed transitions `pending→confirmed→processing→shipped→delivered`, `pending/confirmed→cancelled`; cancellation restores stock and emails the customer), mark COD as paid, print/PDF packing slip (`barryvdh/laravel-dompdf`), resend confirmation email.
- Status change writes `order_status_histories` and fires the right email.

### 7.5 Customers
Read-only list with order count and lifetime value; view shows orders.

### 7.6 Content & settings
- **Contact messages** and **Newsletter subscribers** resources (read, mark handled, export).
- **Settings** page (super_admin): store contact email, admin notification emails, cart TTL hours, low-stock threshold default, COD enabled toggle, VAT rate, announcement bar (text + on/off, rendered above the nav on the storefront when on).
- **Admins** resource with roles.

---

## 8. Backend architecture rules

- Thin controllers; business logic in `App\Services` (`CartService`, `StockService`, `CheckoutService`, `ShippingCalculator`, `OrderNumber`). Every stock or order mutation runs in a DB transaction with row locks.
- FormRequests for all validation. API responses as JSON resources.
- Enums as PHP 8.1 backed enums (`OrderStatus`, `PaymentMethod`, `PaymentStatus`, `StockMovementReason`) with label/colour helpers used by Filament.
- Events: `OrderPlaced`, `OrderStatusChanged`, `StockLow` → listeners send emails.
- Config in `config/kotiva.php` for anything that is not a runtime setting (currency, order-number prefix, VAT rate default).
- Rate-limit `POST /cart/items`, `/contact`, `/newsletter`, `/checkout/payment`.
- CSRF on all forms; the vanilla JS must read the token from `<meta name="csrf-token">` and send it as `X-CSRF-TOKEN`.
- Log every failed stock reservation, every order placement and every status change at `info` level with the order no.

---

## 9. Frontend JS (`public/js/shop.js`) responsibilities

- Add-to-cart buttons (list + PDP), quantity steppers, mini-cart drawer open/close (Escape closes, focus trapped, `aria-*` correct, mirrors `initHamburger` conventions).
- Cart page item updates with optimistic UI and rollback on error.
- Nav cart badge: fetch `/cart/summary` on load, update after every mutation.
- Shipping step: zone→city population, live fee display.
- Checkout submit guard (disable button, spinner, prevent double submit).
- Listing sort control and keeping filter state across sort.
- Toast/notification component styled on the brand for success/error messages.
- Everything progressive: forms work with JS disabled (plain POST + redirect); JS enhances.

---

## 10. Porting the other pages

Port `index`, `about`, `science`, `journal`, `contact`, `ingredients`, `routine-finder`, `privacy-policy`, `terms` to Blade views under the shared layout **without visual change**. Home page bestsellers and the shop teaser rail must read from the DB (`is_best_seller`, active). The Routine Finder keeps its engine untouched and reads products from `/api/products`. The ingredients glossary page is generated from the products' `ingredients` arrays server-side (replacing the old generator). Verify each page pixel-for-pixel against the legacy HTML in both modes.

---

## 11. Deliverables

- Working Laravel app on `feat/shop-v1` with clean, conventional commits per phase.
- `README.md` rewritten: requirements (PHP 8.2+, MySQL 8, Composer, Node for asset copy only), setup (`composer install`, `.env`, `php artisan key:generate`, `migrate:fresh --seed`, `storage:link`, `queue:work`, `schedule:work`), admin login, how to import products, how to add a payment gateway later, and the list of legacy URLs that redirect.
- `.env.example` complete. `docs/ARCHITECTURE.md` (short) describing cart/stock lifecycle and order state machine with a diagram.
- Tests (Pest or PHPUnit, SQLite in-memory): unit tests for `OrderNumber`, `ShippingCalculator`, `StockService` (including concurrent-reservation and negative-stock guards); feature tests for add/update/remove cart, cart expiry purge, the full checkout happy path, out-of-stock at checkout, invalid phone, order status transitions and stock restoration on cancel, product import happy path and row-level failure, every storefront route returns 200, every legacy URL returns 301.

---

## 12. Working method and quality gates

Work in phases; after each phase run the gates below and fix everything before moving on. Report progress at the end of each phase with what was built, what was verified and how.

1. **Scaffold & port** — Laravel install, move assets, shared layout, port all non-shop pages, redirects. Gate: every page renders identically to legacy in light/dark/mobile; `php artisan route:list` clean.
2. **Catalog & seeding** — migrations, models, enums, seeder from legacy data, `/shop`, `/product/{slug}`, `/api/products`, sitemap, SEO parity.
3. **Cart & stock** — CartService, StockService, cookie/token, endpoints, mini-cart, cart page, purge command, tests.
4. **Checkout & orders** — steps, shipping calculator, order creation, order numbers, emails, confirmation, tests.
5. **Dashboard** — Filament, roles, all resources, widgets, import/export, PDF slip, settings.
6. **Hardening** — rate limits, logging, low-stock alerts, announcement bar, accessibility pass, README/docs, final full test run.

Gates that must pass after **every** phase:

- `php -l` on every changed PHP file; `composer validate`; `php artisan config:clear && php artisan optimize:clear`.
- `./vendor/bin/pint --test` (Laravel Pint) with no violations; `./vendor/bin/phpstan analyse` at level 5 with no errors (install Larastan).
- `php artisan test` fully green.
- `php artisan migrate:fresh --seed` succeeds from scratch.
- Boot the app (`php artisan serve`), and using `curl` or a headless browser actually hit every route and check for 200/301 and for no PHP warnings in `storage/logs/laravel.log`.
- Manually walk the end-to-end flow once per phase where applicable: add to cart → change qty → checkout → confirmation → find the order in the dashboard → change status → verify email in `laravel.log` (log mailer) and stock movements table.
- `node --check public/js/shop.js` and load every storefront page with the browser console open: **no JS errors, no 404 assets**.

If anything in this brief conflicts with what you find in the repo, prefer the repo's existing conventions for **look and behaviour**, and this brief for **functionality**. If a requirement is genuinely ambiguous, make the decision a careful senior engineer would make, implement it, and note the decision in `docs/DECISIONS.md` — do not stop to ask unless it is blocking.
