# KOTIVA™ — Storefront and Dashboard

The KOTIVA brand and content website (`klyvosystems.com`) as a Laravel application: the original
pages served from Blade with their design unchanged, the Routine Finder, the ingredient glossary,
and a Filament dashboard for the content.

> **The shop is on Salla** (`shop.klyvosystems.com`). Listing, product pages, cart, checkout,
> orders, prices and stock all live there. Every "Shop", product and cart link on this site lands on
> Salla, and the old `/shop`, `/product/*`, `/cart` and `/checkout` URLs redirect there. Laravel's
> own cart/checkout code is still in the repository but switched off (`KOTIVA_COMMERCE_ENABLED=false`)
> and is due to be deleted in a separate change. See [Salla storefront](#salla-storefront) and D-35.

- **Storefront:** Laravel 12 + Blade, vanilla JS and CSS. No Vite, no Tailwind, no build step.
- **Dashboard:** Filament 3 at `/admin`, with its own login and three roles.
- **Data:** MySQL 8 (tests run on in-memory SQLite).
- **Money:** prices are in SAR and **include VAT**. The VAT figure on orders is the portion contained
  in the price (`total × r / (1 + r)`), never an amount added on top.

Why the code is shaped the way it is — every non-obvious call, and every place this differs from
the original brief — is in [`docs/DECISIONS.md`](docs/DECISIONS.md). How the moving parts fit is in
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Requirements

| | |
|---|---|
| PHP | 8.2+ with `bcmath`, `gd` (WebP support), `intl`, `zip`, `pdo_mysql`, `mbstring`, `fileinfo` |
| Database | MySQL 8 |
| Composer | 2.x |
| Node | Optional: only for `node --check` on the storefront JS and the Routine Finder test (`npm run test:routine`). Nothing is compiled. |

`gd` must be built with WebP: uploaded product images are converted to a 1200px WebP and a 600px
thumbnail. Check with `php -r "var_dump(function_exists('imagewebp'));"`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`: database credentials, `APP_URL`, mail settings, and the `KOTIVA_*` block. Change
`KOTIVA_ADMIN_PASSWORD` in particular (see [Admin login](#admin-login)). Then:

```bash
php artisan migrate:fresh --seed   # 25 products, 3 delivery zones, settings, the first admin
php artisan storage:link           # serves uploaded product images from /storage
php artisan queue:work             # sends order emails; runs large product imports
php artisan schedule:work          # hourly: purge expired carts and release their stock
```

In production, run the queue worker under a process supervisor, and run the scheduler from cron
(`* * * * * php /path/to/artisan schedule:run`) rather than `schedule:work`.

**Without a queue worker,** orders are still placed and still appear in the dashboard. But
confirmation emails and imports of more than 100 rows wait in the `jobs` table until a worker runs.

**Without `storage:link`,** uploaded images are still served: the public disk has `serve => true`,
which adds a `/storage/{path}` route as a fallback. The symlink is faster, since the web server then
serves files directly, so create it wherever the host allows. (On the Windows development machine
it could not be created at all — see D-25.)

## Admin login

Visit **`/admin`**. The first super admin is seeded from `.env`:

```dotenv
KOTIVA_ADMIN_NAME="KOTIVA Admin"
KOTIVA_ADMIN_EMAIL=admin@kotiva.co
KOTIVA_ADMIN_PASSWORD=...
```

- The example password in `.env.example` is public. **The seeder refuses it when
  `APP_ENV=production`.**
- Re-running the seeder never resets an existing admin's password.
- Further admins are created in the dashboard under **Settings → Admins**.

| Role | Can do |
|---|---|
| Super admin | Everything, including store settings and admin accounts. The only role that can delete records. |
| Manager | Catalog, orders, shipping, customers, messages, reports. No settings, no admin accounts. |
| Staff | Orders only: view them, move them through their statuses, print packing slips. |

A deactivated admin is locked out at once. Roles are enforced on the routes themselves, not just
hidden from the menu.

## Salla storefront

The store URLs come from `.env` and are read only through `App\Support\Salla`; no view contains
the store hostname.

```
KOTIVA_SALLA_STORE_URL=https://shop.klyvosystems.com
KOTIVA_SALLA_CART_URL=https://shop.klyvosystems.com/cart
KOTIVA_SALLA_API_BASE=https://api.salla.dev/admin/v2
KOTIVA_SALLA_ACCESS_TOKEN=            # only for API mode below; never commit it
KOTIVA_SALLA_REDIRECT_STATUS=302      # flip to 301 once the Salla store is confirmed final
KOTIVA_SALLA_DASHBOARD_URL=           # optional: the "Open Salla" button on the admin dashboard
KOTIVA_COMMERCE_ENABLED=false         # keep false; Salla owns stock and orders
```

**Product links.** Each product stores its Salla id and path (`salla_product_id`, `salla_path`),
matched by SKU. They are never edited in the dashboard; one command writes them. The standard way to
refresh them is from the exported link file committed at `storage/app/salla-links.json`, which needs
no API token:

```bash
php artisan kotiva:salla-links --from=storage/app/salla-links.json --dry-run   # preview
php artisan kotiva:salla-links --from=storage/app/salla-links.json             # store
php artisan kotiva:llms-txt                                                    # preview /llms.txt (served live; no file to commit)
```

Only the relative product path is stored (`/micellar-water/p1713901761`), never a full URL.
`KOTIVA_SALLA_STORE_URL` owns the origin, and `App\Support\Salla::productUrl()` is the only place
the two are joined, so moving the store to another domain is a one-line `.env` change. With
`--from`, every active product (within `--sku`) must have a row in the file. A missing one is listed
as `no row in file` and fails the run with exit code 1, including with `--dry-run`. Inactive
products may be left out.

With a Salla Merchant API token (`products.read`) in `.env`, `php artisan kotiva:salla-links` (no
`--from`) asks the API for each active SKU instead. Add `--sku=KOT001` to sync one product.

**Deploy must run** `php artisan kotiva:salla-links --check`. It makes no API call and exits 1 if
any active product has no Salla link (such a product's links fall back to the store's home page).

**Admin.** Orders, customers, shipping and stock movements are hidden and refused (403) for every
role; the dashboard shows where to manage them in Salla. On a product, prices, stock and the
low-stock threshold are read-only ("Managed in Salla") and the Salla tab shows the link.

## Doctor referral links

Doctors share `klyvosystems.com/dr/<code>`. The patient lands on this site, and every link to the
Salla store then carries the doctor's Salla affiliate query, so Salla credits the order to them.
Design and trade-offs: `docs/DECISIONS.md` D-38.

**Adding a doctor**

1. In Salla: **Marketing → Affiliate marketing**. Create the affiliate and copy their link (for
   example `https://<store host>/?ref=XXXX`).
2. In this dashboard: **Marketing → Doctor referrals → New**. Enter the name, accept or edit the
   suggested code (e.g. `dr-ahmed`), paste the Salla link and pick a landing page. The form refuses
   a link that is not on the Salla store or has no query string.
3. Copy the **Share link** from the table (`https://klyvosystems.com/dr/dr-ahmed`) and send it to
   the doctor. `…/dr/dr-ahmed?to=shop` skips the landing page and opens the Salla store directly.

**Where to see what**

- Here: visits per doctor (total, unique in the last 30 days, last visit), a 30-day chart and the
  last 100 visits on each doctor's page, and the "Doctor referrals — last 30 days" card on the
  dashboard.
- In Salla's Affiliate marketing dashboard: orders, revenue and commission per affiliate. Salla is
  the source of truth for those. This site never counts orders.

**Behaviour.** The `kotiva_ref` cookie lasts 90 days. It is encrypted, HttpOnly and SameSite=Lax.
The most recent doctor's link wins. An unknown or deactivated code quietly redirects to the home
page and sets no cookie. To stop a doctor's attribution, deactivate them (a bulk action is
available). Their existing cookies stop working at once.

**Testing it**

```bash
php artisan test --filter=Referral
```

Manual check: create a partner with a test affiliate link, open `/dr/<code>`, then click **Shop**.
The Salla URL must end with the affiliate query, e.g. `?filter=cleanser&ref=XXXX`. Place a test
order and confirm it appears under that affiliate in Salla.

## Running the shop day to day (retired)

Kept for reference until the commerce code is deleted; none of this runs while
`KOTIVA_COMMERCE_ENABLED=false`.

- **Orders** move `pending → confirmed → processing → shipped → delivered`. Only pending or
  confirmed orders can be cancelled; a delivered order can be refunded. The status menu only offers
  moves that are allowed. Cancelling returns the stock and emails the customer; shipped and
  delivered also email the customer.
- **Cash on delivery** is recorded separately with **Mark COD paid**, because an order ships while
  still unpaid.
- **Stock** is held when a shopper adds to their cart, released when the cart is emptied or expires,
  and taken for good when the order is placed. Admins change it only through **Adjust stock** on a
  product, or through an import. Every change — shopper, order or admin — is a row in the
  stock-movement ledger, so the number can always be explained.
- **Settings** (super admin only) cover the store email, order-notification emails, cart lifetime,
  default low-stock threshold, cash-on-delivery switch, VAT rate (entered as a percentage) and the
  announcement bar shown above the storefront navigation. Changes apply immediately.

## Importing products

**Products → Import / export → Import from Excel/CSV** takes a `.csv` or `.xlsx` file.
**Download import template** gives you every column with one example row.

**While Salla owns the store** (`KOTIVA_COMMERCE_ENABLED=false`) the import only updates
editorial fields on products that already exist (D-37). The price, compare-at price, stock and
low-stock-threshold columns are still read, so old sheets work. They are labelled
"Ignored — managed in Salla", counted as ignored and never written. A row for an unknown SKU is
refused: create the product in Salla and link it first. The rules below describe the importer with
commerce enabled.

1. **Upload.** The first sheet of the file is read. The header row is matched to fields
   automatically, and each column can be reassigned.
2. **Preview.** The first 10 rows are shown as they will be read with the current mapping.
3. **Check or import.** Every row is validated first, and errors name the line number.

Rules:

- **Required:** `sku`, `name`, `price`, `category`. Everything else is optional.
- **Matching:** products are matched by **SKU**. An existing SKU is updated, a new one is created,
  and a deleted one is restored.
- **Lists** (`benefits`, `ingredients`, `free_from`, `filter_tags`) are separated with a vertical
  bar: `Hydrates|Plumps|Smooths`.
- **Blank cells on an existing product leave that field unchanged.** A sheet with just SKU, name,
  price and category reprices the catalog without touching descriptions.
- **Slugs:** an existing product's slug (its URL) only changes if the file includes a `slug` column
  saying so. New products get one derived from the name.
- **Stock:** `stock_qty` is the level you want. The import records the difference in the stock
  ledger rather than overwriting the number.
- **Categories** are matched by name, ignoring case and spacing ("face care" finds "Face Care").
  An unknown name creates a new category.
- **Images:** `image` is a path such as `assets/products/kot001.webp`, not a web address.
- **Errors:** if any row has an error, **nothing is imported** unless you tick **Skip invalid rows**.
- **Large files:** files of more than 100 rows import in the background (a queue worker must be
  running). The dashboard's bell tells you when the import finishes.

**Export products (CSV)** writes exactly the import's columns, so you can export the catalog, edit
it in Excel and import it straight back. Re-importing an unedited export changes nothing. Orders
can be exported from the Orders list by managers and super admins.

## Adding an online payment gateway later

Checkout reaches payment gateways only through `App\Services\Payments\PaymentGateways`, so a new
provider (Tabby, Moyasar, …) is added without touching the checkout logic:

1. Add a case to `App\Enums\PaymentMethod`, with its `label()`, `shortLabel()` and `color()`.
2. Implement `App\Services\Payments\PaymentGateway`. `initiate()` returns a `PaymentResult` —
   a redirect URL for an off-site provider — and `initialStatus()` is usually `Unpaid` until the
   provider confirms.
3. Register the class in `AppServiceProvider::register()`, next to `CashOnDeliveryGateway`.
4. Add the provider's callback/webhook route, and have it mark the order paid through
   `OrderStatusService::markPaid()`.
5. Add the option to `resources/views/checkout/payment.blade.php`. The page currently shows cash on
   delivery only.

## Legacy URLs

Every URL the static site and the Laravel shop exposed still works:

| Old | New |
|---|---|
| `/index.html` | `/` (301) |
| `/about.html`, `/science.html`, `/journal.html`, `/contact.html`, `/ingredients.html`, `/routine-finder.html`, `/privacy-policy.html`, `/terms.html` | the same path without `.html` (301) |
| `/shop`, `/shop.html`, `/shop?filter=…`, `/shop?routine=…` | the Salla listing, with the filter mapped to a Salla key (D-35) |
| `/product/{slug}`, `/product/{slug}.html`, `/product.html?id=N` | that product's Salla page in one hop (store root if unlinked; 404 for an unknown slug) |
| `/cart`, `/checkout`, `/checkout/*` | the Salla cart |
| `POST/PATCH/DELETE /cart/items…`, `GET /cart/summary`, `POST /checkout/*`, `GET /api/shipping/cities` | **410 Gone** |

Redirects to Salla use `KOTIVA_SALLA_REDIRECT_STATUS` (302 now, 301 after verification). They are
covered by `tests/Feature/StorefrontRoutesTest.php` and `tests/Feature/SallaHandoffTest.php`.

## Quality gates

```bash
composer validate
./vendor/bin/pint --test
./vendor/bin/phpstan analyse          # level 5, Larastan
php artisan test
php artisan migrate:fresh --seed
php artisan kotiva:salla-links --from=storage/app/salla-links.json
php artisan kotiva:salla-links --check   # deploy gate: exits 1 if an active product is unlinked
node --check public/js/shop.js
node --check public/js/layout.js
npm run test:routine                  # Routine Finder engine
```

**Browser automation and view transitions.** The storefront uses a native cross-document view
transition (a 260ms crossfade, in `kotiva.css`). Headless Chromium stops painting after a link click
while it is on, even on a bare two-page test site, so automated browser tests should run with
reduced motion (`reducedMotion: 'reduce'` in Playwright), which the stylesheet already honours.
Check navigation by hand in ordinary desktop Chrome and Safari before launch (D-34).

**Tests run on in-memory SQLite, not MySQL.** They are fast and never touch your database. The cost
is that SQLite ignores `SELECT … FOR UPDATE` and has no exact `DECIMAL` type. So stock locking under
real concurrency and decimal storage are only proven on MySQL itself. **Run the suite once against a
real MySQL 8 database before going live** (D-03, D-28).

## Assets and cache-busting

Every storefront CSS/JS/image reference carries `?v=` + `KOTIVA_ASSET_VERSION`. Bump that one value
in `.env` on any deploy that changes a static asset.

## Fonts

`public/fonts/` ships four self-hosted `.woff2` files: **Futura Now Var**, **Manus**, **Montserrat**
and **Myriad Pro**. Montserrat is open-source (SIL OFL). Futura Now, Manus and in particular Myriad
Pro (an Adobe commercial typeface) need a licence that covers self-hosting on your own
infrastructure. Confirm it before deploying.
