# Decisions

Judgement calls made while implementing `KOTIVA-ECOMMERCE-PROMPT.md`, recorded per §12 of
the brief. Each entry states what was ambiguous or conflicting, what was chosen, and why.

---

## D-01 — Branch: work continues on `e-comm`, not `feat/shop-v1`

The brief opens by placing the work on `feat/shop-v1`. That branch exists, but it is behind:
diffing it against `e-comm` shows 76 files and ~346k lines of divergence, because it predates
the merged brand-colour identity (`Merge pull request #1 from ashrafdiab96/feat/brand-colours`).

Checking it out would have silently discarded the current visual identity — the one thing the
instructions were most explicit about preserving. Work therefore continues on `e-comm`, which
is the tip of the identity work. Nothing is committed or pushed.

## D-02 — Legacy consistency gates retired rather than repointed

Brief §3 says to delete `product/*.html`, `scripts/check-*.js` and `scripts/generate-*.js`.
Those files were the subject of four of the five gates in `npm test`, all green beforehand:
cache-bust token consistency across 38 references, shop/bestseller/glossary data-vs-page drift,
byte-identity of the 25 generated product pages, single-origin URL audit, and header/footer
identity across 12 page families.

Deleting the files those gates assert on retires the gates — they cannot be "kept passing"
against markup that no longer exists. This was raised explicitly and the decision taken was to
follow the brief: retire the static-site gates, keep `npm run test:routine`.

What replaces each retired gate:

| Retired gate | Replaced by |
| --- | --- |
| header/footer identity across 12 pages | `resources/views/layouts/app.blade.php` — emitted once, so it cannot drift |
| cache-bust token consistency | `config('app.asset_version')` — one token, not 38 references |
| generated-page drift | pages render from the database at request time; nothing is pre-rendered |
| single-origin audit | `config('kotiva.site_origin')`, one source for canonical/OG/JSON-LD/sitemap |

`npm run test:routine` is retained and still passes (18 checks). The routine engine, its model
and its test moved to `resources/routine/` with their paths repointed.

`scripts/generate-routine-model.js` was **kept** (moved to `resources/routine/`) although the
brief's delete list names `scripts/generate-*.js`. The two generators the brief actually
describes retiring are the pre-rendered *product* and *ingredients* page generators. This one
regenerates `public/js/routine-model.js` from the authored JSON and is the only way to rebuild
a file the retained test depends on; deleting it would have orphaned the test.

The colour/palette audit scripts (`validate-palette`, `contrast-audit`, `computed-style-audit`,
`colour-*`, `screenshot-*` and `scripts/lib/`) are not mentioned in the brief and scan static
HTML at the repo root. They are preserved under `legacy/scripts/` for reference rather than
deleted, since they encode the brand's colour rules, but they are no longer wired into
`npm test` because their inputs have moved.

## D-03 — MariaDB 10.4 in development, MySQL 8 targeted

The brief specifies MySQL 8. The development machine has XAMPP's MariaDB 10.4.32 and no
MySQL 8. Installing MySQL 8 was offered and declined in favour of using what is present.

Migrations are written to MySQL-8-compatible syntax and avoid MariaDB-only features. The
divergence that matters is §6.5's concurrency requirement: `SELECT … FOR UPDATE` row-locking
semantics are not byte-identical between the two engines. The stock reservation tests therefore
assert on *observable behaviour* (never negative, 422 with the true maximum, movements balance)
rather than on lock internals, so they are meaningful on either engine.

**Before production:** run `php artisan migrate:fresh --seed` and the full suite against a real
MySQL 8 instance. That verification has not been performed locally and is not claimed.

## D-04 — No Vite, no Tailwind on the storefront

Brief §3 forbids a CSS framework and a storefront bundler, and allows Vite "only to copy/version
the static files". Laravel 12's skeleton ships a Tailwind + Vite `package.json` with
`"type": "module"`, which also conflicts with the repo's existing CommonJS scripts.

Vite is omitted entirely. Static assets are served from `public/` as-is and versioned with a
single `config('app.asset_version')` token — the brief's first stated option, and the one that
does not add a build step to a site that never had one. The repo's `package.json` is kept
(CommonJS, Playwright dependency dropped per §3).

## D-05 — `.footer-social`: `aria-label` wins over `aria-hidden`

The footer was *not* byte-identical across the static pages, although the retired
`check-global-consistency.js` reported it as such (it normalised attributes before comparing).
`index.html` alone carried `<div class="footer-social" aria-hidden="true">`; the other ten pages
carried `<div class="footer-social" aria-label="Social media — accounts coming soon">`.

A single shared layout must pick one. The `aria-label` variant is used, on two grounds: it is
what 10 of 11 pages already rendered, and `aria-hidden="true"` directly contradicted the
`role="img"` + `aria-label` marks nested inside it, which would have hidden three labelled
icons from assistive technology. This is the only intentional markup change in the port.

## D-06 — `ingredients.html`'s root-absolute paths

`ingredients.html` was the one page whose nav and overlay used root-absolute `/index.html`,
`/assets/…` paths where every other page used relative ones. Under routing this divergence
disappears: `route()` and `asset()` emit root-absolute URLs for every page. No decision was
needed beyond noting that the two blocks were never actually identical.

## D-07 — `data-lite.js` stays in `public/js/` until phase 2

`science`, `journal` and `routine-finder` read `KOTIVA.ingredients`, `KOTIVA.articles` and
`KOTIVA.getById()` from the legacy bundle. Phase 1's job is a port that is verifiably identical
to the legacy page, so the bundle is copied to `public/js/data-lite.js` to keep those pages
working unchanged.

Phase 2 removes it: products come from the database, the Routine Finder reads `/api/products`,
and the ingredient glossary is generated server-side from the products' `ingredients` arrays.
The journal articles are content with no table in the brief's schema, so they stay client-side.

## D-08 — PHP extensions enabled on the development machine

`gd`, `intl` and `zip` were commented out in `C:\xampp\php\php.ini`. They are required by
Filament's image pipeline (§7.2) and the spreadsheet import (§7.2). The three lines were
uncommented with permission; the original is backed up at `php.ini.bak-kotiva`. This is a local
environment change and touches nothing in the repository.

## D-09 — The SPF filter: `spf` is canonical, `sunscreen` is an alias

The static shop had a pill with `data-filter="sunscreen"`, and its 25 product cards carried
hand-typed `data-tags`. Three of those cards said `sunscreen`. The product data in
`data-lite.js` tags the same three products `spf`.

Both sides were hand-written, so the mismatch never showed — the pill matched the cards, and
nothing compared either against the catalog. Rendering the grid from the database exposed it
immediately: the SPF pill counted 0 where the old page said 3.

`spf` wins, because it is what the data says and what `filterTags[0]` feeds into the product
breadcrumb. `sunscreen` is kept as an alias in `kotiva.shop.filter_aliases`: the footer links,
the old breadcrumb JSON-LD and any indexed `?filter=sunscreen` URL all use it. The listing
301-redirects the alias to the canonical tag rather than quietly accepting both, so there is
one canonical URL and the client-side pill script always finds a pill matching the parameter.

## D-10 — `products.sort_order`, and why the home rail needed it

§6.1 defines the default listing sort as "`is_featured` desc, `sort_order`", but the products
table in §4 has no such column. It was added.

It also fixes a fidelity problem that would otherwise have been invisible. The home page's
bestseller rail was hand-ordered in `index.html` — micellar water, toner, UV balance, acne gel,
ampoules, sun protection, acne control. That sequence is editorial and exists in no data file.
Reading bestsellers from the database in `id` order would have silently reshuffled the rail
while every individual card stayed correct. `sort_order` is seeded from the rail's own order
(1-7 for its members, 100+ for everything else), so the rendered rail is identical to the
static one and the order is now editable from the dashboard instead of living in markup.

## D-11 — The listing's default order changed, deliberately

The static grid rendered in pure catalog order. §6.1 specifies a sort control whose default is
Featured, so `/shop` now leads with the seven featured products. This is a brief-mandated
change rather than a regression, and it is the only ordering change on the storefront: filter
behaviour, card markup and the deep-link handoff are untouched.

## D-12 — The science renderer was ported with its no-loss assertion intact

The retired `generate-product-pages.js` did not simply format the clinical text; it asserted
that the formatting lost nothing. `assertNoLoss()` threw if any client sentence or citation URL
failed to appear in the rendered output, and the generator's own comment is explicit that this
assertion "is the whole basis on which this ships without a claims review".

`App\Support\ScienceRenderer` reproduces both the renderer and the assertion. Dropping the
assertion would have been the single most dangerous silent change in this project: the section
reproduces client-supplied clinical claims on a doctor-positioned brand in a regulated category.

It earned its keep immediately. The first PHP port tracked the current block with a reference
(`$blocks[] = &$cur`), so opening a new block wrote through that reference and overwrote the
previous one — 15 of 25 products silently lost citations or whole paragraphs. The assertion
caught every one. Two further defects surfaced the same way: PHP's `trim()` leaves the
non-breaking spaces the client's text is full of where JavaScript's `trim()` strips them, and
`/u` in PHP enables UCP so `\s` matches U+00A0 in one pattern but not in another that lacked
the modifier.

The rendered output is now verified **byte-identical to all 25 originally generated pages**,
and `tests/Unit/ScienceRendererTest.php` keeps it that way.

## D-13 — Counts the retired gate used to hold true are now derived

`check-consistency.js` verified two hand-maintained numbers on the home page: the header's
"View All 25" and the rail terminus's "18 more products". The markup comment beside the 18 said
plainly that the gate is what stopped it going stale.

Retiring that gate (D-02) would have left two literals with nothing checking them — they would
have silently lied the first time a product was deactivated. Both are now computed from the
catalog: the total from active products, the terminus from active products minus the rail's own
length. The class of bug the gate existed to catch is now impossible rather than merely watched.

## D-14 — The ingredient glossary has two sources, not one

§10 says the glossary is "generated from the products' `ingredients` arrays server-side". That
supplies only half of it. The per-entry copy — canonical name, "also known as", category and the
deliberately conservative, uncited summary — is authored content that no product field contains,
and `legacy/js/ingredients-glossary.js` also carries the `aliases` that reconcile inconsistent
ingredient spellings ("Panthenol (Pro-Vitamin B5)" vs "Pro-Vitamin B5 (Panthenol)").

That curated data moved to `resources/glossary/ingredients.json` and is joined to the live
catalog at request time, which is what makes the "Found in" lists self-maintaining. The glossary
remains a deliberate subset — 22 canonical actives against 61 distinct ingredient strings — and
an entry matching no active product is omitted rather than rendered empty, which is the rule the
old consistency gate enforced. The rendered page is verified against the legacy one: 22 entries,
66 product links, zero differences in slug order or per-entry product lists.

## D-15 — The listing card became a cell, and `initRoutineFilter` followed

§6.1 asks for an Add to cart button on each product card. The card was a single `<a>`, and a
`<form>` cannot legally nest inside an anchor — so the grid child is now a `<div class="product-card">`
holding the product link and the add form as siblings.

The class and the `data-tags` attribute stay on that outer element, which is what `initFilters()`
and `updateGridOrphan()` in `layout.js` select on, so filtering, the pill counts and the
odd-card rule are untouched. `initRoutineFilter()` did need one change: it read `href` from the
`.product-card` itself, which is now the wrapper. It resolves the link from a child anchor,
falling back to the element's own `href` so it works either way.

This is the second change to `layout.js` (see D-16 for the first), and both are the kind §3
anticipated — the file is otherwise reused unmodified, and `markCurrentNav()`'s two regexes are
verified untouched.

Verified after the restructure: 25 cells, 25 links, 25 forms, every pill still matching real
products, and all 25 cells resolving to a slug through the exact regex the routine handoff uses.

## D-16 — `layout.js`'s product-href regex had to change

`initRoutineFilter()` matched product links with `/\/product\/([^/.]+)\.html/`. Product routes are
extensionless now, so that pattern could never match: every card would have been hidden and the
Routine Finder → Shop handoff would have failed closed on the one page it exists to serve.

§3 allows updating a regex where a route genuinely differs. This is that case. `markCurrentNav()`'s
two regexes (`/^\/product\//` and `/\/ingredients$/`) genuinely still match and are untouched.

## D-17 — Cart identity in tests, and a guard that passed for the wrong reason

The cart lives in an httpOnly cookie, and Laravel's test client does not replay a queued response
cookie. Three plausible fixes all failed silently:

| Attempt | Why it failed |
| --- | --- |
| replay the response's cookie via `withCookie()` | `prepareCookiesForRequest()` encrypts again — double-encrypted |
| `withUnencryptedCookie()` with the raw token | `EncryptCookies` cannot decrypt plaintext and nulls the cookie |
| `withCookie()` with the raw token | a probe route showed the request arriving with **no cookies at all** |

Cookies are now passed to `call()` explicitly, encrypted and carrying the `CookieValuePrefix`.

The reason this mattered more than test plumbing usually does: without a cookie, a request is
refused by the "visitor has no cart" branch and never reaches the ownership comparison. The two
ownership tests therefore **passed while exercising nothing**. They are security tests — a cart
line holds a stock reservation, so deleting a stranger's line releases stock they never reserved.
A green test that cannot fail is worse than no test, because it is counted as coverage.

The guard is now verified twice over: in the suite with a genuine cart present, and over real HTTP
where a second visitor holding their own live cart received a 404 and the first visitor's line and
stock were provably untouched.

## D-18 — `refunded` needed a way in

§4 lists `refunded` in the order status enum, but §7.4's transitions never reach it
(`pending→confirmed→processing→shipped→delivered`, `pending/confirmed→cancelled`). Left as written
it would be a state no order could ever occupy.

`delivered → refunded` is allowed, because a refund follows a delivery. `cancelled` and `refunded`
are both terminal: an order that needs to restart is a new order.

## D-19 — VAT is extracted from the total, never added to it

Prices are VAT-inclusive throughout, so `vat_amount` is computed as `total × r / (1 + r)` and
stored for display only. Adding 15% on top would both overstate the tax and change what the
customer pays. Verified end to end: a 494.50 order records 64.50 VAT, not 74.18.

## D-20 — A double submit returns the order, not an empty cart

Placing an order deletes the cart, so the second request of a double-click arrives with no cart.
The controller checked that first and sent the shopper to an empty cart page moments after they
had successfully ordered — no duplicate was created, but the outcome read like a failure.

The spent-token branch now runs before the cart check, so a resubmission answers with the order
that was actually placed. Verified over HTTP: the replay redirects to the confirmation and the
order count stays at 1.

## D-21 — Every city renders, so checkout works without JavaScript

The shipping step originally sent an empty city list until a zone was chosen, assuming JavaScript
would refill it. With JS off a visitor could pick a delivery area and then had no city to select,
making checkout impossible to complete — against §9's requirement that the forms work unaided.

All active cities are now rendered grouped by zone in `<optgroup>`s; `shop.js` narrows them on
zone change, and the server rejects a city that does not belong to the chosen zone either way.

## D-22 — `message` is a reserved name in mail views

`OrderStatusUpdated` passed its body copy as `message`. Laravel injects its own `$message` (an
`Illuminate\Mail\Message`) into every mail view, which shadowed it — Blade then tried to escape a
Message object and threw. It surfaced only once the status listener started firing, and only on
the three tests that triggered a status email.

Renamed to `bodyCopy`, with the reserved name documented in `emails/layout.blade.php` so it is not
reintroduced by the next template.

## D-23 — Order emails are queued; low-stock alerts are debounced

The Mailables are `ShouldQueue` on the database driver, so placing an order never waits on an SMTP
handshake — verified: placement enqueues two jobs and sends nothing inline until a worker runs.

`StockLow` fires only on the decrement that *crosses* the threshold, and the listener additionally
debounces to one alert per product per 24 hours using `Cache::add` (atomic add-if-absent, so two
concurrent sales that both cross cannot both send). An alert that arrives on every subsequent sale
is one people learn to ignore.

## D-24 — `description` and `science` stay plain text, against the brief

§7.2 asks for "rich text/markdown for `description`/`science`". Both fields are given plain
`Textarea`s instead, because the storefront cannot render markup in either one.

`description` is emitted as `{{ $product->description }}` inside a `<p>` — escaped, so any markup an
editor produced would be shown to shoppers as literal tags. `science` is worse: ScienceRenderer
parses it line by line, keying off the client's own "MOA:" and "Ref:" prefixes, and `assertNoLoss()`
throws if a sentence or citation fails to survive rendering. A rich editor would wrap that text in
HTML and break the parse on all 25 approved science sections — the very assertion that lets those
claims ship without a fresh review.

The helper text on both fields says so, so the next person does not read this as an oversight.

## D-25 — Two image conventions, one resolver, and a route instead of a symlink

§7.2 says uploads store to `public/storage/products`. Two things made the literal instruction wrong
here. First, the 25 launch products carry committed brand artwork at `public/assets/products/...`,
addressed relative to the document root — so introducing a second convention without a single
resolver would leave half the storefront working and the other half 404ing the first time an image
was replaced from the dashboard. Second, `public/storage` cannot be created on this machine at all:
`storage:link`, a PowerShell junction and .NET `Directory.Delete`/`CreateDirectory` each fail, two of
them reporting "corrupted and unreadable" on a path that `file_exists()` says is absent.

Uploads therefore still go to the `public` disk exactly as the brief intends, but the disk is marked
`'serve' => true`, which registers a `/storage/{path}` route that serves it with no symlink. In
production the real symlink is served by the web server first and the route is never reached, so it
costs nothing there. The accompanying `PUT` route the flag also registers was checked before being
accepted: `ReceiveFile` requires `?upload=true` plus a valid relative signature, and an anonymous
`PUT` — including a `.php` payload — returns 403 and writes nothing.

`Product::resolveImagePath()` is the single place the two conventions meet; every render site
(storefront, cart, checkout, order confirmation, home rail, cart JSON, routine-finder API) now goes
through it or through `OrderItem::imageUrl()`, which resolves an order's snapshotted path the same
way so old and new orders read identically.

Renditions are generated server-side by `ProductImageService` using GD — 1200px WebP plus a 600px
thumbnail, never upscaled — rather than by Filament's client-side resize, which would make the stored
file depend on whichever browser the admin happened to use.

## D-26 — Stock is not a field on the product form

The generated scaffold exposed `stock_qty` as an ordinary number input. It is not one: StockService
is its sole mutator, so that every change lands in the append-only ledger the column is reconciled
against. A hand-edited number would desynchronise the two with nothing left to reveal it.

On create, the form offers "Opening stock", which is written as the product's first ledger movement
after the row exists rather than straight onto the column — so the two agree from the first record
instead of from the first adjustment. On edit, the quantity is disabled and changes only through an
"Adjust stock" action that calls the service inside its transaction, refuses to go below zero, and is
offered only the two reasons an admin may legitimately choose: `restock` and `manual`. The other four
(`import`, `order_reserved`, `order_released`, `order_fulfilled`) are written by the importer and the
cart and checkout flows; offering them here would let a hand-typed correction masquerade in the
ledger as something an order did, which is the attribution the ledger exists to preserve.

Both dashboard write paths attribute the movement with `Filament::auth()->id()`, not `auth()->id()`.
Admins authenticate on the separate `admin` guard, so the default helper reads an empty web guard and
would have recorded every dashboard movement with nobody attached to it. A test asserts the admin id
on the resulting row, which is how that was caught.

## D-27 — Orders are viewed, never edited

The generated scaffold gave orders a full CRUD form: every column editable, including `status`,
`grand_total` and the shipping snapshot. All three are wrong. The figures are a record of what was
agreed, not a live view, and `status` is not a column but a state machine whose moves return stock to
the catalog, stamp a timestamp, append to `order_status_histories` and decide whether the customer is
emailed. A form writing that column directly would skip all four and leave nothing behind to show it
had.

So the resource registers `index` and `view` only — the create and edit routes do not exist, and a
test asserts their absence rather than trusting the navigation to hide them. Every change goes
through an action that calls OrderStatusService: the status dropdown is populated from
`availableTransitions()` so the UI cannot offer a move the service would refuse, and when one is
asked for anyway the `InvalidOrderTransition` message is shown as-is, because it already names what
is allowed from here. A refused transition writes no audit row; that is asserted, since a timeline
entry for something that did not happen is worse than the refusal itself.

The one column the dashboard writes directly is `admin_note`, which by design carries no side
effects. Marking COD paid is deliberately separate from the status: a COD order ships while still
unpaid, so "delivered" must never imply "paid".

The packing slip is streamed from the action rather than written to disk. It is reproducible from the
order at any moment, so stored copies could only ever go stale — and printing the "collect on
delivery" amount is suppressed once payment is recorded, so a reprint cannot prompt a second
collection.

## D-28 — The suite runs on SQLite; the shop runs on MySQL

Measured, not assumed: a probe test reported `driver = sqlite`, `database = :memory:`, server
version 3.39.2, while dev and production run MariaDB/MySQL. PHPUnit's `<env>` entries in
`phpunit.xml` win over `.env`, so `php artisan test` never touches the `kotiva` database — verified
by row counts before and after a run (25 products, 1 admin, unchanged). Tests are fast and isolated,
which is why this default is kept.

What it costs, stated plainly because D-03 understates it:

* `SELECT … FOR UPDATE` is a **no-op in SQLite**. The stock-reservation tests pass because SQLite
  serialises writes wholesale, not because the row locking works. The locking itself is unverified
  by the suite on either engine. D-03's choice to assert on observable behaviour (never negative,
  422 with the true maximum, movements balance) is what keeps those tests meaningful anyway.
* SQLite has no `DECIMAL` type, so money columns round-trip through `NUMERIC`/float rather than
  MySQL's exact `decimal(10,2)`. Every money calculation goes through bcmath on strings, which is
  what makes the assertions hold under both — but the storage semantics differ.
* Date SQL must be portable. `DATE()` works on both; `DATE_FORMAT()` is MySQL-only and `strftime()`
  is SQLite-only. Verified by running all three against each engine. The dashboard widgets therefore
  group by `DATE(column)`, or they would work in dev and fail in CI.

**Before production**, the full suite must also be run against a real MySQL 8 instance — that is now
required for the concurrency and decimal paths specifically, not merely advisable.

## D-29 — Revenue counts four statuses, not the brief's two

§7.1 describes revenue as "delivered + confirmed orders". The implementation counts four:
`confirmed`, `processing`, `shipped`, `delivered` — the set already encoded in
`OrderStatus::countsAsRevenue()` in an earlier phase, and shared by `Order::scopeRevenue()` and the
customer lifetime-value column.

Read literally, the brief would make committed money disappear and reappear: an order confirmed on
Monday counts, stops counting on Tuesday when it moves to `processing`, and counts again on Friday
when it is delivered. The total would fall whenever the shop was busiest at packing. Since
`pending` (not yet accepted), `cancelled` (stock returned) and `refunded` (money returned) are all
correctly excluded, the four-status set is read as the brief's intent and its two-status phrasing as
shorthand for "orders that count".

This interpretation previously existed only as a docblock with no test defending it, which is how
such a rule gets silently flipped later. `OrderStatusRevenueTest` now pins the exact set, so changing
it becomes a deliberate act with a failing test attached.

## D-30 — Shipping rates are history, and the city import runs inline

**Rates.** `shipping_rates` has no unique constraint on `zone_id`, and `ShippingZone::activeRate()`
is `latestOfMany()` over the active rows — so the schema deliberately keeps a zone's rate history.
That design has one sharp edge: two active rates leave the older one silently *shadowed* rather than
visibly wrong, and the shop quietly charges a fee nobody chose.

So `ShippingRate::activate()` makes a rate exclusive in a transaction — it retires the zone's other
active rows and keeps them as history rather than deleting them. The relation manager on the zone
page calls it after any create or edit that leaves a rate active, and offers "Use this rate" on a
retired one. The zones table carries a "Quotable" column because an active zone with no active rate
cannot be quoted at all, and `ShippingQuote` treats that as *unavailable*, which is emphatically not
free delivery — without the column such a zone simply looks fine.

§7.3 also states that changing a rate must never alter existing orders. That holds by construction:
CheckoutService writes `shipping_fee` once at placement and nothing reads a rate back afterwards. It
was asserted nowhere, so `ShippingAdminTest` now pins it — a placed order keeps the 25.00 it was
charged while new quotes move to 60.00.

**City import.** Filament ships `ImportAction`, and its three tables are migrated. It is not used
here: it dispatches a queued `Bus::batch`, and dev runs the `database` queue driver, so an upload
would report success and then do nothing at all until a worker ran. A city list is a handful of short
rows, so `CityCsvImporter` runs inline and reports what it did immediately.

It upserts on `(zone_id, name_en)` — the table's own unique key — so re-importing a file is
idempotent exactly as re-running ShippingSeeder is; blind inserts would throw on the second run. It
strips the UTF-8 BOM Excel writes (otherwise the first city is named "\u{FEFF}Riyadh" and duplicates
on the next import), skips a detected header row, reports rows with no city name instead of dropping
them silently, and fills a blank Arabic name but never overwrites one an admin typed by hand.

## D-31 — Settings that actually govern, and a bar that retunes rather than rewrites

Two of the fields §7.6 asks for were decorative when I found them. `vat_rate` and
`low_stock_threshold` were seeded into the settings table, but every reader consulted
`config()` — `CheckoutService` for VAT, `ProductResource` for the new-product default. An admin
could have edited VAT on the dashboard, seen it saved, and had every subsequent order keep the old
rate: the tax on the receipt would simply have been wrong. Both now read
`Setting::get(key, config(...))`, the same pattern `cart_ttl_hours` and `cod_enabled` already used,
with config as the pre-row fallback. `CheckoutFlowTest` pins it end to end — a rate of 5% yields
14.28 VAT on a 300.00 order where the config default gives 39.13.

VAT is entered as a percentage and stored as a rate. A field storing what the admin literally typed
would turn "15" into 1500% VAT, which is the kind of error that looks fine in the form and is
catastrophic on every order afterwards.

Access is `canAccess()`, not `shouldRegisterNavigation()`. Filament consults the former before
registering navigation *and* aborts 403 on mount and hydrate, so one override both hides the page
and refuses the URL; hiding alone would have been cosmetic. A test types the URL as a manager and
as staff to prove it.

Invalid notification addresses are refused by the form rather than silently filtered, so the admin
is told and the list that was already delivering order mail is left intact — verified by probe:
nothing at all is written when validation fails, not even the page's other fields. Blank entries are
a separate case: Laravel's `email` rule treats an empty string as absent, so validation passes and
`save()` drops it. Both behaviours were measured before being asserted, after two wrong assumptions
about which one applied.

**The announcement bar** is styled entirely in `shop.css`. `kotiva.css` fixes `.nav` at `top:0` with
`height:var(--nav-h)` and offsets page content with `calc(var(--nav-h) + …)` in six separate rules.
Rather than edit any of them, `html.has-announcement` retunes `--nav-h` to include the bar so every
one of those offsets self-corrects, and `.nav`'s own height is re-pinned since it would otherwise
grow with the token. The bar text is `nowrap` with an ellipsis on purpose: if it wrapped, the
`--announce-h` token would be a lie and the nav would sit on top of the content it is meant to clear.

## D-32 — Product import: two passes, the ledger, and a page instead of a modal

**Two passes.** `ProductImporter::validate()` reads every row and writes nothing; `import()` then
writes the valid rows in one transaction. That makes the brief's all-or-nothing rule a decision taken
on a finished report, not something discovered after half the catalog has changed. "Skip invalid
rows" is an explicit opt-in on the page.

**Rules a careless import would break quietly**, each pinned by `ProductImportTest`:

* Stock is a *target level*, applied as the difference through `StockService` with reason `import`.
  The column is never written directly, so the ledger still sums to `stock_qty`. An unchanged level
  writes no movement (the service rejects a zero delta anyway).
* On an update, an empty or unmapped cell leaves the field alone — a repricing sheet of four columns
  must not wipe every description. An existing product's slug never changes unless the file says so;
  it is a live, indexed URL.
* Categories match on `Str::slug(name)`, exactly as ProductSeeder creates them, so "Face Care" finds
  the seeded row instead of cloning it. New slugs are de-duplicated (`kotiva-serum-2`); an
  Arabic-only name falls back to the SKU.
* A soft-deleted SKU is restored and updated. The SKU index covers trashed rows, so "create" would
  otherwise die on an integrity error no admin could act on.
* An image *URL* is refused: `Product::resolveImagePath()` treats anything outside `assets/` as a
  storage path, so it would render as a broken `/storage/https://…` image rather than fail visibly.
* Filter tags are free-form slugs, not limited to the six pills. Seven launch products carry
  `cleanser`, which has no pill; the pills come from config, so an extra tag cannot invent one.
  Restricting them would have made an unedited export fail to re-import. The same fact exposed a bug
  in the product form: its tag checkboxes listed only the pills, leaving `cleanser` in the data where
  an admin could neither see nor remove it. It now lists the product's own extra tags, marked
  "no pill", and a test proves saving keeps them.

**Round trip.** The product export uses exactly the import's columns. Re-importing an unedited export
of the real 25-product catalog reports 25 unchanged, 0 updated, and leaves 13 compared columns
identical — `science` byte for byte. That needed one correction found while planning the test:
the reader originally trimmed every cell, which would have rewritten whitespace in the approved
science texts that ScienceRenderer's output is checked against. Long-text fields now keep their
exact content.

**Formula injection.** Every exported cell goes through `CsvExports::safe()`, which prefixes `'` to
anything a spreadsheet would execute (`=`, `+`, `-`, `@`, tab, CR). Order exports carry text
customers typed; a name of `=HYPERLINK(…)` would otherwise run on whichever admin opened the file.
The importer reverses the guard so it survives a round trip. The order export follows the
customer-data capability: staff work orders but do not download the customer list.

**Format is chosen, not guessed.** The reader is picked from the upload's own extension.
openspout's `ReaderFactory` is deprecated precisely because guessing is brittle.

**A page, not a modal.** The brief asks for "a Filament action on the Products list". The action
exists, and it opens a dedicated page. Preview, remapping, a row-level error report and a re-check
need state that survives several round trips, and a modal that closes on the first validation error
is the wrong container for that. The upload is copied to private storage at once, because Livewire's
temporary file can be garbage-collected before a queued job reaches it.

**Queued above 100 rows**, as the brief says. The job re-validates rather than trusting the page's
earlier check, is not retried (a half-understood failure re-run automatically could apply the same
stock change twice), deletes its file, and reports through the panel's notification bell. That
needed the notifications table and `->databaseNotifications()`, neither of which existed. The bell
reports start and finish, not a percentage: the brief's "progress notification" is met at that grain.

## D-33 — Phase 6 hardening: what the audit found, not just what the brief listed

The §8 rules were already in place: all four named endpoints throttled, FormRequests everywhere,
`X-CSRF-TOKEN` from the meta tag, and placements, status changes and refused reservations logged
with the order number or SKU. Checking them turned up four real gaps, each now fixed and pinned
by a test.

1. **Two of the four throttles were never tested.** Contact and newsletter were; `POST /cart/items`
   and `POST /checkout/payment` were configured but unproven. `HardeningTest` now drives each past
   its limit and requires a 429.
2. **A published default admin password.** `.env.example` ships `KOTIVA_ADMIN_PASSWORD=ChangeMe!2026`.
   Copy that file to production, run `--seed`, and the store has a super admin whose password is in
   the repository. `AdminSeeder` now **throws** on that exact value when `APP_ENV=production`. It
   throws rather than warns, because a warning in a deploy log gets read after the account exists.
   Local setup is unaffected. `.env.example` also now defaults to MySQL, the production target, not
   SQLite.
3. **Checkout errors were visible but silent.** A failed shipping submit showed 14 error elements
   on screen, yet no field carried `aria-invalid` or `aria-describedby` and nothing was announced. A
   screen-reader user would submit a bad phone number and hear nothing. Each error is now tied to its
   field, and a `role="alert"` summary is announced on load. `CheckoutAccessibilityTest` requires
   both and checks that a clean form carries no error markers. The rest of the audit, run over HTTP
   with a real session across 14 storefront pages including cart and checkout, was clean: alt text,
   labels, accessible names, unique IDs, valid `aria-*` references, skip-link target, one `h1` per
   page. The audit was itself checked against planted faults. The mini-cart already closed on
   Escape, trapped focus and returned it.
4. **The payment seam was a comment, not code.** `PaymentGateway`'s docblock promised the checkout
   "talks to this interface, never a concrete class", yet `CheckoutController` injected
   `CashOnDeliveryGateway`, so a second gateway would have needed controller surgery. A small
   `PaymentGateways` registry, bound in `AppServiceProvider`, is now the only thing checkout sees. A
   reflection test fails if a concrete gateway is ever injected again. Found while writing the
   README's "how to add a gateway" section: the instructions could not be written truthfully until
   this was fixed.

## D-34 — What the real-browser run found

The §12 browser gate, run with Playwright (Chromium 149), passed: 11 storefront pages × light
desktop, dark desktop and a 390px phone, with no console errors, no failed or 4xx/5xx requests, no
horizontal overflow on mobile, and dark mode confirmed as actually applied. The full walk passed too:
add to cart, change quantity, checkout, order, dashboard sign-in, find the order, confirm it, cancel
it. Underneath, three queued emails were sent, the phone typed as `0551234567` was stored as
`+966551234567`, both status moves were attributed in the history, and the stock ledger returned to
exactly 50 with its sum matching `stock_qty`. Getting there surfaced two things.

**1. Headless Chromium stops painting after any link click, because of the site's view transition.**
`kotiva.css` has used `@view-transition { navigation: auto; }` (a 260ms crossfade) since the
initial commit. In headless Chromium, both the headless shell and the full "new headless" mode, a
page reached by clicking a link produces no frames at all, so screenshots and clicks time out. This
is **not** a KOTIVA bug and nothing in this work caused it. It reproduces on a bare two-page HTML
site containing only that one rule, with no Laravel, no KOTIVA CSS and no JS (no frame in 10s). Remove
the rule and the page paints in 65–77ms; on KOTIVA pages, stripping it at the network layer restores
painting in 632ms. The rule is left in place: it is part of the approved visual identity and has
shipped on the live static site since the start.

**Not verified:** whether windowed desktop Chrome is affected. Checking that would have meant
opening a browser window on the developer's machine unasked. The browser walk therefore runs with
`reducedMotion: 'reduce'`, a path the site already supports (the same stylesheet turns the transition
off for reduced-motion visitors), so the real production CSS and JS are exercised unchanged. **Before
launch, click through a few pages in ordinary desktop Chrome and Safari.** If navigation ever
appears to hang for a few seconds, this rule is the first suspect.

**2. The dashboard was the only part of the product that reached third-party hosts.** The
storefront makes zero external requests, because its fonts are self-hosted. Filament's defaults did
not: Inter came from `fonts.bunny.net` (in this environment one request timed out and the dashboard
waited on it), and each avatar came from `ui-avatars.com`, which sends every admin's name to a third
party on every page. The panel now uses the brand's own self-hosted Montserrat — the storefront's
`@font-face`, pointing at the same file — and `InitialsAvatarProvider` draws the initials as an
inline SVG. A browser run confirms zero external requests on both halves, and `HardeningTest` fails
if either host reappears.

The same run also showed why "the dashboard returns 200" was never enough. The first screenshot
showed every widget as an empty box. Those were Filament's lazy-load placeholders, captured before
the widgets loaded; once settled, all six rendered with the right figures and 4 charts. The distinction
was only visible by looking.
