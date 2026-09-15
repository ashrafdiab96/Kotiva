# KOTIVA — E-commerce Implementation Brief (FINAL)

You are working inside the `Kotiva` repository on branch `feat/shop-v1`. Read this brief fully before touching any file. Then read the repo itself (`README.md`, `js/layout.js`, `js/data-lite.js`, `js/data-full.js`, `css/kotiva.css`, `shop.html`, `product.html`, `scripts/generate-product-pages.js`, `docs/COLOUR-MIGRATION.md`) so you understand the existing design system, data and validation tooling before you write code.

---

## 1. Context

KOTIVA is a premium skincare brand (25 SKUs, prices in SAR, VAT-inclusive). The repo is a **hand-built static site**: vanilla HTML/CSS/JS, no framework, no backend. It was delivered by an agency and is production-quality: strong design system, three-theme CSS, light/dark mode, a Routine Finder quiz engine, pre-rendered product pages for SEO, and consistency-check scripts. The brand colour migration (`feat/brand-colours`) has already been applied; the palette validator (`scripts/validate-palette.mjs`) and audit scripts from that work are part of the repo's test suite and **must keep passing**.

The client wants the site turned into a **real e-commerce store** with an **admin dashboard**, under two hard constraints:

1. **Same source code.** No migration of the frontend to React/Next/Vue or any SPA framework. The existing HTML, CSS and vanilla JS stay and are extended.
2. **Same visual identity.** New pages must look like they were designed by the same people who designed the current site.

Agreed technical direction: **Laravel + MySQL 8** backend, **Blade** for server-rendered pages reusing the existing markup, **Filament** for the admin dashboard. Payment is **Cash on Delivery only** in v1; online payment (Tabby / Moyasar / similar, Saudi market) comes later and must plug in without redesigning orders.

**Versions:** use the latest *mutually compatible* stable Laravel + Filament versions available at implementation time. Verify compatibility before scaffolding, record the selected versions in `docs/DECISIONS.md`, and commit `composer.lock`. Do not upgrade to a newer major version during this task.

---

## 2. Non-negotiable rules

### 2.1 Frontend
- Do not change the look, spacing, typography, colours, animations or behaviour of existing pages except where this brief requires it. When you port a page to Blade, the rendered HTML must be functionally identical to the original.
- Reuse `css/kotiva.css`, `js/layout.js`, `js/animations.js`, fonts and all assets as they are. New CSS goes in a **new file** (`public/css/shop.css`) using the existing design tokens (`--font-display`, `--accent`, `--accent-text`, `--border`, `--bg-card`, `--nav-h`, `--radius-pill`, `--k-*`, etc.) and the existing component classes (`.btn`, `.btn-gold`, `.filter-pill`, `.product-card`, `.pdp-*`, `.accordion-*`). Only approved palette colours and alphas — `validate-palette.mjs` will reject anything else. No Tailwind/Bootstrap on the storefront.
- New frontend JavaScript is **vanilla ES2017-compatible**, in a new file (`public/js/shop.js`), following the IIFE + `'use strict'` style of `layout.js`. No jQuery, no bundler on the storefront. Alpine.js is acceptable **only** inside Filament.
- Every page must work in both light and dark mode (`html[data-mode="dark"]`) and on mobile (≤ 768px). Mirror the existing dark-mode selectors in `kotiva.css` for new components.
- Progressive enhancement: every form works with JS disabled (plain POST + redirect); JS enhances.

### 2.2 Backend authority
- **All money and stock values are computed server-side from DB/config.** Clients never submit `unit_price`, `subtotal`, `shipping_fee`, `vat_amount`, `grand_total`, `stock_qty`, `payment_status` or `status`; strip/ignore them at the FormRequest level and never mass-assign them.
- Every stock mutation goes through `StockService`, and every order status change goes through `OrderStatusService`. Nothing writes `products.stock_qty` or `orders.status` directly — including Filament, seeders and imports.
- Order totals, prices, shipping, VAT, customer contact and shipping metadata are **immutable snapshots** on the order once created. Later changes to products, rates, VAT settings or customer records never alter an existing order.

### 2.3 Safety and environment boundary
- **Git:** before any edit run `git status --short`, `git rev-parse HEAD`, `git branch --show-current`; confirm the branch is `feat/shop-v1`; record any dirty files in `docs/DECISIONS.md`. Never overwrite or revert pre-existing user changes. No destructive git commands. **Do not commit or push** — keep changes logically grouped by phase and provide recommended conventional commit messages in your phase reports.
- **Database:** `migrate:fresh`, `db:wipe`, `DROP`, destructive seeds and test-DB commands are local/test only. Before any destructive DB command verify `APP_ENV` and the configured database target.
- **Scope:** build and configure local development only. Do **not** deploy, configure DNS, send real customer email, configure real SMTP or payment credentials, integrate a real gateway, touch a production database, or invent production business values (shipping fees, ETAs, thresholds, VAT treatment) — seed such values as clearly labelled development defaults and list them for client confirmation.
- **Privacy:** logs use `order_no`, internal IDs, status and non-sensitive operational metadata only. Never log addresses, phone numbers, emails, checkout payloads or payment metadata.
- No placeholder code, no `TODO` left behind, no stubbed methods, no fake data in production paths. **Zero syntax errors and zero logical errors.** Run the app, run the tests, and walk every flow before reporting a phase done (§12).

---

## 3. Target repository layout

Convert the repo root into a Laravel application. Keep git history; move files with `git mv`.

```
app/                      Models, Http, Services, Enums, Events, Listeners, Filament, Mail, Jobs, Policies
bootstrap/  config/  routes/  database/  resources/  tests/  storage/
public/
  index.php
  css/kotiva.css          moved (unchanged)      css/shop.css   NEW
  js/layout.js  js/animations.js  js/routine-engine.js  js/routine-model.js   moved (unchanged)
  js/shop.js              NEW
  fonts/  assets/  img/  robots.txt  llms.txt   moved
resources/views/
  layouts/app.blade.php   ONE shared header / mobile menu / footer (+ announcement bar slot)
  pages/                  home, about, science, journal, contact, ingredients, routine-finder, privacy-policy, terms
  shop/ index, show       cart/ index       checkout/ review, shipping, payment, confirmation
  emails/                 order-placed-customer, order-placed-admin, order-status, low-stock, contact-received, newsletter-welcome
  components/             product-card, price, cart-badge, breadcrumb, stepper, toast …
resources/routine/        routine-model.json + test-routine-engine.mjs (moved from data/ and scripts/)
legacy/                   ORIGINAL static files kept for reference: *.html, product/*.html, scripts/generate-*.js,
                          scripts/check-*.js, js/data-*.js, data/ — outside public/, served by no route
docs/                     DECISIONS.md, ARCHITECTURE.md, COLOUR-MIGRATION.md (existing), visual-baseline/
scripts/                  existing colour/palette/screenshot audit tooling (kept), plus visual-regression runner
```

Rules for the port:

- Extract header, mobile overlay and footer **once** into `layouts/app.blade.php`. `scripts/check-global-consistency.js` becomes redundant only after every page is served through that layout — then move it to `legacy/` rather than delete it.
- **Do not broadly delete `package.json` or its tooling.** Audit existing scripts/dependencies; keep `validate-palette.mjs`, the colour audit scripts, the screenshot tooling and Playwright (used for visual regression); keep `test:routine` working from the new location; move — not delete — the old generators and consistency checks to `legacy/` only **after** Blade output parity, SEO parity and product-data migration are verified. They are the reference for JSON-LD shape, product structure and the ingredients page.
- `legacy/` is outside `public/`; no route serves it; nothing in it becomes downloadable.
- Keep `js/layout.js`'s current-nav detection working (`markCurrentNav()` derives the active link from `location.pathname` and expects `/shop`, `/product/<slug>`, `/ingredients`). Define routes so those patterns match; touch the regexes only if a route genuinely differs. Test on every page.
- Contact and newsletter forms currently POST to an external n8n webhook. Replace with `POST /contact` and `POST /newsletter` that validate, store (`contact_messages`, `newsletter_subscribers`) and notify admins. Add a honeypot field plus rate limiting; newsletter subscription is idempotent on normalised (trimmed, lower-cased) email with a UNIQUE constraint. Keep the existing front-end success/error UI.
- Cache-busting: replace every `?v=xxx` with `asset()` plus one version string from `config('kotiva.asset_version')`, or Vite with `@vite` **only** to copy/version static files (no transpiling of storefront JS). Keep the existing version-token consistency check passing or move it to `legacy/` with a note.
- SEO parity: every product page renders server-side the same `<title>`, meta description, canonical, Open Graph tags and `Product` JSON-LD the old generator produced (see `legacy/scripts/generate-product-pages.js`). JSON-LD `availability` reflects real stock. `/sitemap.xml` is generated from the DB.

---

## 4. Database schema

Laravel migrations, MySQL 8, `utf8mb4`. Money is `DECIMAL(10,2)` SAR. All tables have timestamps; soft-delete where noted. **Extensible discriminators are `VARCHAR(32)` validated by PHP backed enums, never MySQL `ENUM`.** Declare FK `onDelete` behaviour explicitly in every migration.

**categories** — `id, name, slug (unique), description, sort_order, is_active`. Index `is_active`.

**products** (soft deletes) — `id, sku (unique), slug (unique), name, category_id FK→categories RESTRICT, product_line (nullable varchar — brand line from the guideline, e.g. cleansing/skin-repair/toner/sunscreen/hand-care/whitening/hair; kept separate from storefront category), skin_type, concern, action, volume, price, compare_at_price (nullable), description text, benefits json, how_to_use text, science longtext, ingredients json, free_from json, filter_tags json, image (relative storage path), gallery json, is_featured, is_best_seller, is_active, sort_order int default 0, published_at datetime nullable, stock_qty unsigned int, low_stock_threshold int default 5, low_stock_alerted_at datetime nullable, meta_title, meta_description, weight_grams nullable`. Indexes: `category_id, is_active, is_featured, is_best_seller, stock_qty, published_at, sort_order`.

Taxonomy rule: the legacy data distinguishes storefront category (`category`), `skinType`, `concern`, `filterTags` and — via the brand guideline — product line. **Map each into its own column; never collapse them into one field or infer one from display labels.** Record the mapping in `docs/DECISIONS.md`.

**product_stock_movements** — `id, product_id FK RESTRICT, delta signed int, reason varchar(32) [manual, import, restock, order_placed, order_cancelled], reference_type, reference_id, note, admin_id nullable FK SET NULL, created_at`. Indexes: `product_id`, `created_at`, `(reference_type, reference_id)`. `products.stock_qty` is the cached current value; every change is a movement.

**shipping_zones** — `id, name, is_active, sort_order`.
**shipping_cities** — `id, zone_id FK RESTRICT, name_en, name_ar, is_active`. Index `(zone_id, is_active)`.
**shipping_rates** — `id, zone_id FK RESTRICT, fee, free_shipping_threshold nullable, estimated_days_min, estimated_days_max, is_active`.

**carts** — `id, token uuid unique, customer_email nullable, expires_at, last_activity_at`.
**cart_items** — `id, cart_id FK CASCADE, product_id FK RESTRICT, qty, unit_price_snapshot`. UNIQUE `(cart_id, product_id)`. Products are soft-deleted, so deletion never cascades into carts; `CartService` detects inactive or soft-deleted products on every cart read and removes the affected item gracefully with a notice to the user.

**customers** — `id, first_name, last_name, email unique (normalised), phone (normalised +966), marketing_opt_in`. Index `phone`. Guest checkout **finds-or-creates by normalised email and updates name/phone**; it never fails because an email exists.

**orders** — `id, order_no unique, checkout_token uuid unique, customer_id FK SET NULL, customer_email, customer_phone, status varchar(32), payment_method varchar(32), payment_status varchar(32), currency 'SAR', subtotal, shipping_fee, discount_total, vat_amount, grand_total, shipping_zone_id FK SET NULL, shipping_city_id FK SET NULL, shipping_zone_name, shipping_city_name, shipping_eta_min, shipping_eta_max, shipping_first_name, shipping_last_name, shipping_phone, shipping_address_line1, shipping_address_line2, shipping_district, shipping_postal_code, customer_note, admin_note, placed_at, confirmed_at, shipped_at, delivered_at, cancelled_at, paid_at nullable, paid_by_admin_id nullable FK SET NULL, ip_address, user_agent`. Indexes: `customer_id, status, payment_status, placed_at, shipping_zone_id, shipping_city_id`.

**order_items** — `id, order_id FK CASCADE, product_id FK SET NULL, sku_snapshot, name_snapshot, image_snapshot, unit_price, qty, line_total`. Indexes `order_id`, `product_id`.

**payments** — `id, order_id FK CASCADE, method varchar(32), provider nullable, provider_reference nullable, amount, currency, status varchar(32) [unpaid, paid, refunded], paid_at nullable, metadata json nullable`. COD v1 creates one `cod` payment with status `unpaid` per order. Index `order_id`, `status`.

**order_status_histories** — `id, order_id FK CASCADE, from_status, to_status, admin_id nullable FK SET NULL, note, created_at`.

**settings** — `key unique, value, type` accessed only through a cached `SettingsService` that validates/casts (invalidate cache on update). Keys: store contact email, admin notification emails, COD enabled, cart TTL hours, default low-stock threshold, VAT rate, shipping VAT treatment (see §6.7), announcement bar text/on-off.

**contact_messages**, **newsletter_subscribers** (`email` unique, normalised), **admins** (Filament users; roles `super_admin`, `manager`, `staff` via `spatie/laravel-permission` or Filament Shield). Laravel `jobs`, `failed_jobs`, `cache`, `sessions` tables.

**Seeding.** `ProductSeeder` reads `legacy/js/data-lite.js` and `legacy/js/data-full.js` (extract the array literal; it is valid JSON) and inserts the 25 products with all fields and the taxonomy mapping above; `published_at` = null for legacy imports (see §6.1 sort); initial `stock_qty` 50 each via `StockService` with reason `restock`. Shipping zones/cities/rates and thresholds are seeded as **clearly labelled development defaults** unless the client has supplied values — list them in `docs/DECISIONS.md` as requiring confirmation. Super-admin from `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` env vars (never committed; README tells operators to change the password immediately). `php artisan migrate:fresh --seed` produces a fully working local store.

---

## 5. Routes (storefront)

```
GET  /                         home
GET  /shop                     listing
GET  /product/{slug}           product page (301 from /product.html?id=N and /product/{slug}.html)
GET  /about /science /journal /contact /ingredients /routine-finder /privacy-policy /terms
POST /contact  POST /newsletter

GET  /cart                     cart page
POST /cart/items               add   {product_id, qty}
PATCH /cart/items/{item}       update qty
DELETE /cart/items/{item}      remove
GET  /cart/summary             JSON (count, subtotal) for the nav badge

GET  /checkout                 → /checkout/review
GET  /checkout/review
GET  /checkout/shipping   POST /checkout/shipping
GET  /checkout/payment    POST /checkout/payment      (places the order)
GET  /checkout/confirmation/{order_no}   signed, expiring URL AND placing-session check — order_no alone is never authorisation

GET  /api/products             JSON for the Routine Finder (id, slug, name, price, image, filter_tags, in_stock)
GET  /api/shipping/cities?zone_id=
GET  /sitemap.xml
```

301 redirects for every old static URL (`/shop.html` → `/shop`, etc.).

---

## 6. Storefront features

### 6.1 Listing page (`/shop`)
Same hero, filter pills, 3-column grid and `.product-card` markup, rendered from the DB (`is_active` only). Filter pills keep the client-side `data-tags` behaviour driven from `filter_tags`; counts computed server-side. Add a **sort control** in the pill style: Featured (default: `is_featured` desc, `sort_order`, `name`), Price low→high, Price high→low, Name A→Z, Newest (`published_at` desc nulls last, then `created_at`). Sort is a query param handled server-side; filter state persists across sort changes. Cards show price, "VAT incl." and a compact **Add to cart** button beside "Discover →". Out-of-stock products show a "Sold out" state (muted, button disabled). Keep the `?routine=` and `?filter=` deep links working.

### 6.2 Product page (`/product/{slug}`)
Port the PDP 1:1. Replace "Where to Buy" with price block, quantity stepper (1 … min(10, stock)), **Add to cart**, stock indicator ("In stock" / "Only N left" when ≤ threshold / "Sold out"), and a "Cash on delivery available" note. Adding opens a **mini-cart drawer** (new component on existing tokens) with items, subtotal, "View cart" / "Checkout"; the nav badge updates without reload. Related: same category, active, in-stock first, max 4. Inactive or soft-deleted product → 410 (or 404 — decide, record in `docs/DECISIONS.md`); admin can still see it via its orders.

### 6.3 Cart (`/cart`)
Items with image, name, unit price, stepper, line total, remove; summary card with subtotal, "Shipping calculated at checkout", VAT note, total; empty state. All quantity changes go through the endpoints and totals are re-rendered from the server response.

### 6.4 Cart persistence
Cart identified by a `kotiva_cart` cookie holding the cart token: **HttpOnly, SameSite=Lax, Secure in production, Path=/**, expiry matching the cart TTL. The token is never exposed to JS. TTL = `settings.cart_ttl_hours` (default 72); `last_activity_at` refreshes on every mutation. Scheduled `carts:purge` (hourly) deletes expired carts; also lazily treat an expired cart as empty on access.

**Price authority:** `cart_items.unit_price_snapshot` is the price shown when the item was added; `products.price` is authoritative until order placement. On cart/review/checkout access compare them; if changed, show old → new, use the current price for totals, and update the snapshot after notifying. Only `order_items.unit_price` is immutable.

### 6.5 Stock handling
**No reservation at cart time.** Adding/updating a cart item validates `qty ≤ stock_qty` and `is_active` and returns 422 with the maximum available on failure; it does not change stock. Stock is decremented **at order placement only**, inside the order transaction (§6.6). If stock changed while the customer shopped, review/payment shows "Only N of {product} are currently available — please update your cart" and blocks placement until fixed. `stock_qty` never goes negative. Cancellation restores stock exactly once (idempotent — §7.4). Implement cart-time reservations only if the client explicitly requires inventory holds (record in `docs/DECISIONS.md`).

### 6.6 Checkout (three steps + confirmation)
Shared stepper component (Review → Shipping → Payment); each step its own route; server session holds progress; skipping ahead redirects back.

- **Review:** read-only cart summary, edit link, price-/stock-changed warnings.
- **Shipping:** first name, last name, email, phone (KSA: `^(\+966|0)?5\d{8}$`, normalised to `+9665XXXXXXXX`), zone, city (populated from `/api/shipping/cities` on zone change, server-rendered fallback), address line 1, line 2 (opt), district, postal code (opt), note (opt), marketing opt-in. Fee and ETA shown live once a zone is chosen; free-shipping threshold applied. FormRequest validation; errors inline in the contact-form style.
- **Payment:** single "Cash on Delivery" radio (pre-selected), full summary incl. shipping and grand total, terms checkbox, "Place order" (disabled while submitting).
- **Order placement** (`CheckoutService::place`) — one transaction: `SELECT … FOR UPDATE` every cart product → verify active, current price, `stock_qty ≥ qty` → find-or-create customer → compute totals (§6.7) → insert order (with `checkout_token`), order_items, `cod` payment (unpaid), status history → decrement stock via `StockService` (reason `order_placed`, reference = order) → **commit**. Only after commit dispatch `OrderPlaced` (`dispatch()->afterCommit()`); mail/queue failure never rolls back or duplicates an order.
  - **Idempotency:** the payment form carries a `checkout_token` UUID generated when the review step is entered and stored in session; `orders.checkout_token` is UNIQUE. A second submit with the same token (double-click, retry, concurrent request) returns/redirects to the already-created order instead of creating another. The token is rotated after success.
  - **Order number** (`App\Support\OrderNumber`): `KOT-XXXXXX`, 6 chars from `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` via `random_int`. The UNIQUE index is authoritative: attempt the INSERT, catch the duplicate-key exception, regenerate, retry (max 5). Never pre-check with SELECT. Internal `id` stays separate from the public number. Unit-tested.
- **Confirmation:** order number, ETA, itemised summary, address, "Continue shopping". Signed expiring URL plus placing-session check.
- **Payment seam:** minimal `PaymentGateway` interface (`initiate(Order): PaymentResult`, `method(): PaymentMethod`) with `CashOnDeliveryGateway`. Do not invent callback/webhook/refund/tokenisation APIs until a gateway is chosen. Adding one later = new enum value + implementation + controller; orders/payments schema unchanged.

### 6.7 Totals and VAT
Computed server-side at placement and stored as snapshots: `subtotal` (sum of current prices × qty), `shipping_fee` (rate snapshot, 0 if threshold met), `discount_total` (0 in v1), `vat_amount`, `grand_total`. Prices are VAT-inclusive; the VAT contained in an inclusive amount is **`amount × rate / (100 + rate)`** (15% → `× 15/115`), rounded half-up to 2 dp — never `× 15%`. Whether shipping is VAT-inclusive/taxable is a **business/accounting decision**: read it from `settings.shipping_vat_treatment` (`inclusive` | `exempt`), default `inclusive`, and flag it in `docs/DECISIONS.md` as requiring client/accountant confirmation. Do not invent accounting treatment.

### 6.8 Emails
Queued Mailables (`ShouldQueue`, database driver, retry-safe, `afterCommit`), Blade HTML styled to the brand (logo, display headings with system fallback, approved accent): `OrderPlacedCustomer`, `OrderPlacedAdmin` (with dashboard link), `OrderStatusUpdated` (shipped / delivered / cancelled), `LowStockAlert`, `ContactMessageReceived`, `NewsletterWelcome`. Low-stock debounce is persisted in `products.low_stock_alerted_at` (once per 24 h; reset to null when stock rises above threshold so a future crossing alerts again). `MAIL_MAILER=log` default; SMTP vars documented in `.env.example`, never filled in.

---

## 7. Admin dashboard (Filament)

Installed at `/admin`, branded (logo, approved accent, dark mode). Auth + roles: `super_admin` full; `manager` everything except settings and admins; `staff` orders read/update only. **Authorisation is enforced server-side by policies/permissions, not by hidden navigation, and is tested (§11).**

### 7.1 Dashboard home
Widgets, each labelled by its exact definition: **Orders placed** (count by status), **Order value** (sum of `grand_total` of non-cancelled orders), **Delivered sales** (status `delivered`), **Collected revenue** (`payment_status = paid`), average order value, 30-day line chart of orders and collected revenue, bar chart of top 10 products by qty sold (non-cancelled), low-stock table, latest 10 orders. Date-range filter. Never label confirmed COD orders as revenue.

### 7.2 Catalog
- **Categories:** CRUD, reorderable, active toggle, product count.
- **Products:** CRUD with tabs (General, Content, Media, Inventory, SEO); repeaters for `benefits`, `ingredients`, `free_from`, `filter_tags`; toggles; SKU/slug auto-generated but editable; `sort_order`, `published_at`. Table: image, SKU, name, category, price, stock (colour-coded), status; filters; bulk activate/deactivate. Soft delete only; a product referenced by orders remains visible in order history; deleting never touches `order_items`.
- **Images:** through the Laravel `Storage` public disk (`config('kotiva.media_disk')`), never a hard-coded path. Validate MIME from content, configurable max size, UUID filenames under `products/{product-id}/{uuid}.webp` (never overwrite — new file, update DB path, delete old derivatives after the DB update succeeds), preserve aspect ratio, strip metadata, generate 1200px WebP + 600px thumbnail. Store relative paths.
- **Stock:** "Stock" tab with current qty, add/remove form (reason + note → `StockService` → movement), movement history; read-only **Stock Movements** resource.

### 7.3 Shipping
Zones (CRUD, active, sort), Cities (CRUD, EN/AR, active, CSV bulk import), Rates on the zone page. Changing a rate never alters existing orders (snapshots).

### 7.4 Orders
Table: order no., customer, city, items, grand total, payment status, status badge, placed at; filters; global search by order no./email/phone. View: full order, items, address, status timeline, admin notes, customer's order count. Actions go through `OrderStatusService::transition(order, to, admin, note)` which validates the transition, updates status + timestamps transactionally, writes history, restores stock **exactly once** on cancellation (guarded by checking for an existing `order_cancelled` movement for that order inside the same transaction), and dispatches after-commit events/emails. Transitions: `pending→confirmed→processing→shipped→delivered`; `pending|confirmed|processing → cancelled` (never after `shipped`/`delivered`). **Mark COD paid** (manager/super_admin): sets `payment_status = paid`, `orders.paid_at`, `paid_by_admin_id`, and the payment record. `refunded` exists in schema for future gateways only — **no refund workflow in v1** (document). Packing slip PDF (`barryvdh/laravel-dompdf`); resend confirmation email.

### 7.5 Customers — read-only list with order count and lifetime value; view shows orders.

### 7.6 Content & settings — Contact messages and Newsletter subscribers (read, handled, export); Settings page (super_admin) via `SettingsService`; Admins resource with roles.

### 7.7 Operational tooling (Phase 6, after the core store works)
- **Import products from XLSX/CSV** (`maatwebsite/excel` or `openspout`): preview first 10 rows with column mapping; validate every row (required sku, name, price, category; arrays `|`-separated); if any row fails, import nothing unless "skip invalid rows" is ticked; upsert by SKU; downloadable template; queued with progress for > 100 rows. **Stock in an import is a delta applied through `StockService` with reason `import`** — never a direct overwrite of `stock_qty`.
- CSV export of products and orders. Advanced widgets, low-stock alert tuning.

---

## 8. Backend architecture rules

Thin controllers; logic in `App\Services` (`CartService`, `StockService`, `CheckoutService`, `OrderStatusService`, `ShippingCalculator`, `SettingsService`, `OrderNumber`). PHP backed enums (application requires PHP 8.2+) (`OrderStatus`, `PaymentMethod`, `PaymentStatus`, `StockMovementReason`) with label/colour helpers used by Filament; DB columns are varchar validated by these enums. FormRequests for all validation; JSON resources for API. Events `OrderPlaced`, `OrderStatusChanged`, `StockLow` → listeners dispatched after commit. `config/kotiva.php` for non-runtime config (currency, order-number prefix, VAT default, asset version, media disk). Rate-limit `POST /cart/items`, `/contact`, `/newsletter`, `/checkout/payment`. CSRF on all forms; storefront JS reads `<meta name="csrf-token">` and sends `X-CSRF-TOKEN`. Session/CSRF cookies use production-secure settings (`SESSION_SECURE_COOKIE`, `SameSite=Lax`).

---

## 9. Frontend JS (`public/js/shop.js`)

Add-to-cart (list + PDP), quantity steppers, mini-cart drawer (Escape closes, focus trapped, correct `aria-*`, mirrors `initHamburger` conventions), cart page optimistic updates with rollback, nav badge via `/cart/summary`, shipping zone→city population and live fee, checkout submit guard (disable + spinner), sort control with preserved filter state, brand-styled toast. All progressive.

---

## 10. Porting the other pages

Port `index`, `about`, `science`, `journal`, `contact`, `ingredients`, `routine-finder`, `privacy-policy`, `terms` to Blade under the shared layout **without visual change**. Home bestsellers and the shop rail read from the DB. Routine Finder keeps its engine and reads `/api/products`. Ingredients glossary is generated server-side from `products.ingredients` (replacing the old generator). Verify each page against the legacy HTML with the visual-regression baseline (§12, Phase 0) in both modes and both viewports.

---

## 11. Deliverables

- Working Laravel app on `feat/shop-v1`, uncommitted, grouped by phase with recommended commit messages.
- `README.md` rewritten: requirements (PHP 8.2+, MySQL 8, Composer, Node for asset copy and audits), setup (`composer install`, `.env`, `key:generate`, `migrate --seed`, `storage:link`, `queue:work`, `schedule:work`), admin login and first-password change, import how-to, how to add a payment gateway, legacy URL redirects. `.env.example` complete, credentials empty.
- `docs/DECISIONS.md` (versions, taxonomy mapping, dev-default business values needing confirmation, VAT shipping treatment, 404/410 choice, any judgement calls) and `docs/ARCHITECTURE.md` (cart/stock lifecycle, order state machine diagram, checkout transaction sequence).
- **Tests.** Pest/PHPUnit. SQLite in-memory is fine for ordinary unit/feature tests: `OrderNumber`, `ShippingCalculator` (incl. VAT-inclusive maths and threshold), `SettingsService`, cart add/update/remove/expiry, checkout happy path, out-of-stock at checkout, price-changed handling, invalid phone, status transitions, mark-paid audit fields, every storefront route 200, every legacy URL 301, `legacy/` not routable, **authorisation** (staff cannot manage products/settings/admins; manager cannot manage settings/admins; super_admin can), mass-assignment rejection of authoritative fields, import happy path and row-level failure. **Concurrency tests run against MySQL 8** (dedicated `.env.testing.mysql` / CI job): simultaneous placement of the last unit, negative-stock prevention under row locks, checkout idempotency under concurrent submits, order-number collision retry, double cancellation restoring stock once.

---

## 12. Phases and gates

Work in phases. After each phase run **the gates applicable to that phase**; from the first phase where a flow exists it becomes mandatory in every later phase; the final phase runs everything. Report per phase: what was built, what was verified and how, recommended commit message, open decisions.

**Phase 0 — Audit & baseline.** Git safety (§2.3). Repository audit. Framework/package compatibility decision → `docs/DECISIONS.md`. Legacy data → schema taxonomy mapping. **Visual baseline:** with the existing `scripts/screenshot-pages.mjs` (or an extension of it) capture every page family at desktop and 390px, light and dark, fixed browser/viewport, saved under `docs/visual-baseline/` (or git-ignored artefacts with a committed report), before any change.
**Phase 1 — Scaffold & port.** Laravel, assets moved, shared layout, all non-shop pages, redirects, contact/newsletter, preserved npm tooling. Gate: every page renders identically to the baseline (geometry/wrapping/visibility compared, not memory); `route:list` clean; `npm test` and `validate-palette.mjs` pass.
**Phase 2 — Catalog.** Schema, models, enums, seeder, `/shop`, `/product/{slug}`, `/api/products`, sitemap, SEO/JSON-LD parity against the legacy generator output.
**Phase 3 — Cart.** `CartService`, cookie, endpoints, mini-cart, cart page, purge command, price-change handling, tests.
**Phase 4 — Checkout & orders.** Shipping, repricing, MySQL-locked placement, idempotency, snapshots, payments record, COD, confirmation, emails, tests incl. MySQL concurrency.
**Phase 5 — Core dashboard.** Filament, roles/policies, categories, products, images, stock, orders + `OrderStatusService`, customers, shipping, settings, metric widgets.
**Phase 6 — Operational tooling.** Import/export, packing slip, low-stock alerting, advanced widgets.
**Phase 7 — Hardening.** Security review (cookies, CSRF, rate limits, mass assignment, `legacy/` exposure, PII logging), accessibility pass on new components, full MySQL concurrency suite, visual regression of all pages vs baseline (differences only where e-commerce UI was intentionally added — list them), README/docs, final full run.

Gates:
- `php -l` on changed PHP files; `composer validate`; `php artisan optimize:clear`.
- `./vendor/bin/pint --test` clean; `./vendor/bin/phpstan analyse` level 5 clean (Larastan).
- `php artisan test` green; MySQL concurrency suite green (Phase 4+).
- `php artisan migrate:fresh --seed` succeeds from scratch (local DB only).
- `npm test` green, including `validate-palette.mjs` on all storefront CSS/HTML/Blade-rendered output.
- Boot the app, hit every route with `curl`/headless browser (200/301), no PHP warnings in `storage/logs/laravel.log`.
- Walk the end-to-end flow (Phase 4+): add → change qty → checkout → confirmation → dashboard → status change → email in log → stock movement rows.
- `node --check public/js/shop.js`; every storefront page loads with no console errors and no 404 assets.
- Visual regression vs baseline (Phase 1+).

If this brief conflicts with what you find in the repo, prefer the repo's conventions for **look and behaviour** and this brief for **functionality**. Resolve ordinary ambiguity like a careful senior engineer, implement, and record the decision in `docs/DECISIONS.md`; stop to ask only when blocked.

---

## 13. Execution instruction

Do not attempt to implement the entire brief in one uncontrolled pass. Execute Phase 0 first, report its findings, then continue sequentially through the phases. Do not skip a phase or its applicable gates. If a gate fails, fix it before continuing. Do not weaken, remove or bypass an existing test merely to make the suite pass.
