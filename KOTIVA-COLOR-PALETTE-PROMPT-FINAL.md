# KOTIVA — Brand Colour Migration Brief (FINAL, aligned to "Amendment 0")

You are working in the `Kotiva` repository. This task is a **colour-only** change. Read this brief fully, then read `css/kotiva.css` end to end (design tokens at the top, three `[data-theme]` blocks after them, dark-mode overrides from ~line 1640) before editing anything.

---

## 0. Source-of-truth priority

When instructions appear ambiguous, resolve in this order:

1. Official KOTIVA Brand Guidelines — base colours and category ownership
2. Amendment 0 — intended website colour direction and visual application
3. Brand invariants in this brief (§2.1) — implementation / accessibility decisions
4. Semantic role of the existing component
5. Existing code structure and behaviour

Never reinterpret an official category colour merely to preserve the old website's visual hierarchy. If accessibility conflicts with an official light category colour, keep the official colour for non-text treatment and use the explicitly approved accessible text substitute defined here.

---

## 1. Goal

Implement **Amendment 0 — "Adjust & Switch Website Colors to Kotiva's"** from the *Kotiva Website Amendments* document: replace the current warm beige/brown system with the approved **blue, purple, white, black and red** palette, using red **selectively** for handwritten/script typography in the general UI, while red retains its approved Skin Repair product-category role.

**Nothing else changes.** No layout, spacing, typography, content, markup, animations, JavaScript behaviour, images, or copy. A visitor sees exactly the same site with different colours. If you find yourself changing anything that is not a colour value, stop — you have left scope. Amendments 0.5–6 (hero video, quiz visuals, product-card differentiation, aspect ratios, newsletter, galleries, reviews) are **not** part of this task.

### Implementation principle

This is **not** a blind `old HEX → new HEX` replacement. It is `old semantic colour role → approved Kotiva semantic colour role`. The same bronze value is currently used for text, borders, buttons, icons and decoration; those occurrences may need different approved colours depending on role. Map by role.

### Ambiguous occurrences

If an existing colour occurrence cannot be confidently classified by role, do **not** guess from its HEX family. Inspect the component and its computed usage. If still ambiguous: (1) leave that occurrence unchanged for now; (2) record file, line, selector/component and old value in `docs/COLOUR-MIGRATION.md`; (3) continue with unambiguous mappings; (4) report all unresolved occurrences before considering the task complete. An unresolved occurrence may remain temporarily during implementation, but an old/unapproved colour cannot pass the final palette gate; any unresolved occurrence containing one leaves the migration **BLOCKED** unless the designer explicitly approves it as an exception.

---

## 2. Approved colours

Source: KOTIVA Brand Guidelines (colour palette page + monochrome logo) and the Amendment 0 mockup.

| Token (add to `:root`) | Official name | Pantone | Hex | RGB | Approved role(s) |
|---|---|---|---|---|---|
| `--k-white` | White | — | `#FFFFFF` | 255,255,255 | primary light page background; text on dark surfaces |
| `--k-black` | Logo black | — | `#231F20` | 35,31,32 | primary ink / dark neutral; dark-mode surface; logo |
| `--k-blue-light` | Cleansing | 278 C | `#8AB7E9` | 138,183,233 | Cleansing category; light accent surfaces, rules, borders, icons; accent text on **dark** surfaces only where contrast passes — **never text on white/light** |
| `--k-blue` | Hand Care | 300 C | `#005DBA` | 0,93,186 | Hand Care category; accessible text-blue substitute; clinical accent |
| `--k-purple` | Hair | 7663 C | `#6A3277` | 106,50,119 | Hair category; approved editorial (dark section) surface per the Amendment 0 mockup |
| `--k-red` | Skin Repair | 1795 C | `#D7282F` | 215,40,47 | Skin Repair category; selective handwritten/script typography; error states |
| `--k-lilac` | Toner | 7438 C | `#D39DD8` | 211,157,216 | Toner category |
| `--k-green` | Cleansing (deep) | 3308 C | `#004539` | 0,69,57 | Cleansing category (deep variant); "free-from" pills |
| `--k-orange` | Sunscreen | 158 C | `#EC7725` | 236,119,37 | Sunscreen category only |
| `--k-grey-blue` | Whitening | 538 C | `#C0CEDB` | 192,206,219 | Whitening category — surfaces only, never small text |

### 2.1 Brand invariants (override every generic mapping below)

- `#D7282F` = Skin Repair category + selective handwritten/script typography + error states.
- **Every `--script` token in every theme and mode resolves to `#D7282F`.**
- `#EC7725` is not a global accent; it is the Sunscreen category colour.
- `#D39DD8` is the official Toner colour. Where it cannot meet text contrast, `#6A3277` may be used as an accessibility-safe **text** treatment while `#D39DD8` remains the Toner surface colour. This does not redefine purple as the Toner colour.
- `#6A3277` is the official Hair colour and may additionally serve approved editorial surfaces.
- `#C0CEDB` is the official Whitening colour. Where it cannot meet text contrast, `#005DBA` may be used as the accessible text colour while `#C0CEDB` remains the Whitening surface colour.
- `#8AB7E9` must not be used as text on white or light surfaces.
- `#005DBA` carries accent text on **light** surfaces. On approved **dark** surfaces (`#6A3277`, `#231F20`), `#8AB7E9` may carry accent text where programmatic WCAG verification passes.
- `#005DBA` may act as an accessible text substitute where a light category colour cannot meet contrast, without redefining that category's official colour.
- `#FFFFFF` is the primary light page background. `#231F20` is the primary ink / dark neutral.
- `--accent-deep` is a **legacy semantic token**. Its name does not imply that its resolved colour must be darker. Preserve the token/API to avoid structural changes; map it per theme to the appropriate high-contrast interaction colour (it is `#005DBA` on light themes and `#FFFFFF` on dark surfaces). Do not rename or restructure tokens.

### 2.2 Colour-introduction rule

Only the ten approved base colours above may be introduced. Derived transparency is permitted **only** through the explicitly approved alpha variants listed here. Do not introduce additional solid HEX/RGB colours, `color-mix()`, lighten/darken operations, HSL/HSLA values, or arbitrary tints. `#000` / `#000000` / `rgba(0,0,0,α)` are an allowed exception **only** where they already exist in shadows and are intentionally retained; log each retained occurrence.

Approved alpha variants and controlled alpha families (complete **policy**; any combination outside these rules requires designer sign-off and must be logged in `docs/COLOUR-MIGRATION.md`). Four kinds of entry:
- **fixed combinations** — exact values listed in the table;
- **existing-shadow family** — `rgba(35,31,32,α)` / `rgba(0,0,0,α)` at an alpha already used by that shadow before migration;
- **editorial-overlay family** — `rgba(106,50,119,α)` at an alpha already used by that overlay before migration;
- **status family** — `rgba(0,69,57,α)` / `rgba(215,40,47,α)` starting from the existing alpha, governed by the status alpha rule below.


| Purpose | Value |
|---|---|
| Secondary surface (`--bg-alt`: footer, filter bar) | `rgba(138,183,233,0.18)` on white |
| Card surface (`--bg-card`) | `rgba(138,183,233,0.10)` on white |
| Mid text (`--fg-mid`) | `rgba(35,31,32,0.72)` |
| Dim text (`--fg-dim`) | `rgba(35,31,32,0.62)` |
| Borders (`--border` / `--border-strong`) on light | `rgba(35,31,32,0.12)` / `rgba(35,31,32,0.30)` |
| Shadows on light | `rgba(35,31,32,α)`, existing shadow alphas only (shadow family) |
| Text on dark surfaces (`--fg-mid` / `--fg-dim`) | `rgba(255,255,255,0.78)` / `rgba(255,255,255,0.64)` |
| Borders on dark surfaces | `rgba(255,255,255,0.18)` / `rgba(255,255,255,0.40)` |
| Dark-mode and editorial secondary / card surfaces | `rgba(255,255,255,0.04)` / `rgba(255,255,255,0.07)` |
| Editorial photo overlays / gradients | `rgba(106,50,119,α)`, starting from the existing alphas |
| Hover on accent-bordered controls | `rgba(138,183,233,0.25)` background |
| Category surface tints (only where a category indicator already exists) | official category colour at `0.15`; `#C0CEDB` at `0.40` |
| Status / "free-from" / error tints | `rgba(0,69,57,α)` / `rgba(215,40,47,α)` — see alpha rule below |
| Clinical borders (`--border` / `--border-strong`) | `rgba(0,93,186,0.15)` / `rgba(0,93,186,0.40)` |
| Dark-mode strong/interactive accent border (existing DEC-159 filter-pill rule and equivalents) | `rgba(138,183,233,0.60)` — only where an existing strong/interactive accent border already uses that alpha |
| Near-opaque dark navigation / sticky surface (`.nav.scrolled`, `.filter-sticky` in dark mode) | `rgba(35,31,32,0.97)` — only where the existing nav/sticky surface already uses a near-opaque background |
| Editorial existing dark layer | `rgba(35,31,32,0.20)` — only where an existing editorial rule already applies a layered darkening; do not introduce the layer elsewhere |

**"Same alpha" is permitted only when the resulting alpha is explicitly present in this table.** Otherwise use an approved semantic alpha from the table or log the occurrence for designer sign-off. Old bronze alphas are not carried into the blue system by default.

**Alpha rule for status and overlay colours:** start with the existing alpha and verify the final composited contrast/visibility programmatically. If it fails, do not choose an arbitrary new alpha. Record the failure in `docs/COLOUR-MIGRATION.md` and use an alpha already present in the table above where semantically appropriate. If no approved alpha solves the issue, require designer sign-off before introducing another alpha. Never introduce another base colour. This matters most for error states, where visibility is functional.

Fallback if the designer rejects alpha variants: `--bg-alt`/`--bg-card` = `#FFFFFF` with `--border` separators; `--fg-mid`/`--fg-dim` = `#231F20`; overlays = `#6A3277` at the existing alphas (overlays are inherently translucent).

All contrast ratios mentioned anywhere in this brief are **expected values, not authoritative**. Compute WCAG contrast programmatically against the final **composited** foreground and background (rgba surfaces depend on what is beneath them). If a combination fails, change the semantic role/token selection — never introduce a new colour.

---

## 3. Pre-edit audit — mandatory, before changing any file

1. Inventory every existing CSS custom property related to colour (all four token blocks and the legacy aliases).
2. Inventory every unique HEX / RGB / RGBA / HSL / HSLA literal and named colour in `css/`, `js/`, `scripts/`, root `*.html`, `product/*.html`, both generator scripts' templates, and SVG/text assets — **including literals inside `var(--token, <fallback>)` arguments, gradient/shadow/filter functions, and SVG `fill`/`stroke`/`stop-color`/`flood-color`/`lighting-color` attributes**. No old-palette value may remain hidden inside a CSS custom-property fallback.
3. Include colour-bearing properties that component-oriented audits miss, where they already exist: `color-scheme`, `accent-color`, `caret-color`, `outline-color`, `text-decoration-color`, `column-rule-color`. Do not add these properties if absent.
4. Classify every occurrence by semantic role: background/surface · text · border · icon · button · script typography · overlay · shadow · status/error · product category.
5. For each occurrence in a generated file (`product/*.html`, `ingredients.html`), record whether it originates from a generator template.
6. Capture the **alpha baseline**: the set of existing alpha values used by retained shadows, editorial overlays and status treatments, per occurrence. The palette validator must validate the controlled alpha families against this audited baseline; an alpha is not "existing" merely because it appears after the migration.
7. Record `git status --short` and the current commit SHA. Do not overwrite or revert pre-existing user changes; the final diff audit must distinguish migration changes from changes already present before this task.
8. Write the inventory (file, line, value, role, generator-owned?, proposed target), the alpha baseline and the git state to `docs/COLOUR-MIGRATION.md` **before** any replacement.
9. Never perform a blind global replacement where one old value serves multiple roles; map each occurrence by its role.

---

## 4. Old → new mapping (by role)

Apply everywhere a colour appears: CSS custom properties, hard-coded literals in `kotiva.css`, inline `style=""` and `<style>` blocks in every `*.html` and `product/*.html`, generator templates, `<meta name="theme-color">`, SVG assets, colour literals in `js/*.js`. JSON-LD blocks are text — leave them.

### 4.1 Theme RITUAL (default light) — `[data-theme="ritual"], html`

| Token | Old | New |
|---|---|---|
| `--bg` | `#F7F2EB` | `#FFFFFF` |
| `--bg-alt` | `#EDE7DC` | `rgba(138,183,233,0.18)` |
| `--bg-card` | `#F3EDE4` | `rgba(138,183,233,0.10)` |
| `--fg` | `#1A1714` | `#231F20` |
| `--fg-mid` | `#5A5550` | `rgba(35,31,32,0.72)` |
| `--fg-dim` | `#6D6760` | `rgba(35,31,32,0.62)` |
| `--accent` | `#C4956A` | `#8AB7E9` |
| `--accent-light` | `#D4A87A` | `#8AB7E9` |
| `--accent-deep` | `#8B6442` | `#005DBA` |
| `--accent-text` | `#8B5F37` | `#005DBA` |
| `--border` / `--border-strong` | bronze @ 0.20 / 0.50 | `rgba(35,31,32,0.12)` / `rgba(35,31,32,0.30)` |
| `--script` | `#8B3A2A` | `#D7282F` |
| `--shadow` / `--shadow-hover` | `rgba(26,23,20,…)` | `rgba(35,31,32,…)` same existing shadow alphas — permitted by the shadow family in §2.2 |

Legacy aliases in `:root`: `--off-white`, `--cream` → `#FFFFFF`; `--black` → `#231F20`; `--bronze` → `#8AB7E9`; `--muted` → `rgba(35,31,32,0.62)`; `--border-color` → `rgba(35,31,32,0.12)`.

Every rule where `--accent` (or a bronze literal) currently colours **text** on a light surface must be switched to `--accent-text`. Audit each `color:` declaration for this — it is the most common role error in this migration.

### 4.2 Theme EDITORIAL (dark photographic sections) — `[data-theme="editorial"]`

Per the Amendment 0 mockup, editorial sections become purple.

| Token | Old | New |
|---|---|---|
| `--bg` | `#1C1815` | `#6A3277` |
| `--bg-alt` | `#231F1B` | `#6A3277` (layer `rgba(35,31,32,0.20)` above it only where the rule already layers) |
| `--bg-card` | `#2A2520` | `rgba(255,255,255,0.07)` |
| `--fg` | `#F7F2EB` | `#FFFFFF` |
| `--fg-mid` / `--fg-dim` | cream @ 0.70 / 0.55 | `rgba(255,255,255,0.78)` / `rgba(255,255,255,0.64)` |
| `--accent` / `--accent-light` | `#D4A87A` / `#E8C090` | `#8AB7E9` |
| `--accent-deep` | `#C4956A` | `#FFFFFF` (legacy token; see invariants) |
| `--accent-text` | `#D4A87A` | `#8AB7E9` — permitted on this dark surface only after programmatic verification |
| `--border` / `--border-strong` | gold @ 0.20 / 0.50 | `rgba(255,255,255,0.18)` / `rgba(255,255,255,0.40)` |
| `--script` | `#C4956A` | `#D7282F` (display size; verify at 3:1 — see resolution rule below) |
| `--shadow` / `--shadow-hover` | `rgba(0,0,0,…)` | keep (documented exception) |

Hero overlays and gradients in editorial sections (`rgba(12,8,4,…)`, `rgba(26,23,20,…)`, `#0C0804`, `#0F0A04`, …) → `rgba(106,50,119,α)` starting from the same alphas, so the photograph reads through a purple veil as in the mockup; verify legibility of the text over the veil.

**Script-on-purple resolution rule:** if `#D7282F` script text fails the applicable WCAG threshold directly on `#6A3277`, do not silently change `--script` to white, blue or any other colour. Preserve `#D7282F`; first establish whether the script is decorative/non-essential or qualifies as large text (≥24px regular / ≥18.66px bold, implement as ≥19px → 3:1). If it is functional text and still fails, document the conflict for designer sign-off rather than violating the brand invariant.

`.btn-primary` on editorial surfaces: the mockup shows a white pill with black text — `background:#FFFFFF; color:#231F20; border-color:#FFFFFF`; hover `background:#8AB7E9; color:#231F20`.

### 4.3 Theme CLINICAL (white science sections) — `[data-theme="clinical"]`

| Token | Old | New |
|---|---|---|
| `--bg` / `--bg-alt` / `--bg-card` | `#FFFFFF` / `#F5F7F8` / `#F0F2F4` | `#FFFFFF` / `rgba(138,183,233,0.18)` / `rgba(138,183,233,0.10)` |
| `--fg` / `--fg-mid` / `--fg-dim` | `#0D1117` / `#4A5260` / `#63676F` | `#231F20` / `rgba(35,31,32,0.72)` / `rgba(35,31,32,0.62)` |
| `--accent` / `--accent-light` / `--accent-deep` / `--accent-text` | `#2A4A8A` / `#3D6DB5` / `#1E3A6A` / `#2A4A8A` | `#005DBA` |
| `--script` | `#2A4A8A` | **`#D7282F`** (script is red in every theme) |
| `--border` / `--border-strong` | `rgba(42,74,138,0.15/0.40)` | `rgba(0,93,186,0.15)` / `rgba(0,93,186,0.40)` |
| `--shadow` / `--shadow-hover` | `rgba(13,17,23,…)` | `rgba(35,31,32,…)` same existing shadow alphas — permitted by the shadow family in §2.2 |

Do not recreate the old clinical light/deep tonal hierarchy with invented blue shades. Where all clinical accent tokens resolve to `#005DBA`, preserve hierarchy through existing borders, opacity, typography and structure only. Do not alter layout or introduce new colour values.

### 4.4 Dark mode — `html[data-mode="dark"]`

| Token / rule | Old | New |
|---|---|---|
| `--bg` / `--bg-alt` / `--bg-card` | `#1A1510` / `#211A14` / `#261E18` | `#231F20` / `rgba(255,255,255,0.04)` / `rgba(255,255,255,0.07)` |
| `--fg` | `#F0EBE3` | `#FFFFFF` |
| `--fg-mid` / `--fg-dim` | `rgba(240,235,227,…)` | `rgba(255,255,255,0.78)` / `rgba(255,255,255,0.64)` |
| `--accent` / `--accent-light` | (inherit) | `#8AB7E9` |
| `--accent-text` | (inherit) | `#8AB7E9` — **only** after programmatic contrast verification against `#231F20`; if it fails, use `#FFFFFF` |
| `--accent-deep` | (inherit) | `#FFFFFF` (legacy token; see invariants) |
| `--border` / `--border-strong` | bronze @ 0.22 / 0.50 | `rgba(255,255,255,0.18)` / `rgba(255,255,255,0.40)` |
| `--script` | `#D9A066` | `#D7282F` |
| `.nav.scrolled`, `.filter-sticky` backgrounds | `rgba(26,21,16,0.97)` | `rgba(35,31,32,0.97)` |
| `.filter-pill` border (DEC-159 rule) | `rgba(196,149,106,0.60)` | `rgba(138,183,233,0.60)` |
| other `rgba(196,149,106,…)` in dark-mode rules | | `rgba(138,183,233,…)` only at an alpha present in §2.2; otherwise the nearest approved semantic alpha, logged |

Editorial sections inside dark mode keep the purple `--bg` from §4.2. Verify the `dark-mode × data-theme` combinations (dark+ritual, dark+editorial, dark+clinical) explicitly.

### 4.5 Skip link (`.skip-link`) — theme-explicit, never via `--accent-deep`

Because `--accent-deep` resolves to white on dark surfaces, do not define the skip link with it.

- Ritual / Clinical / light: `background:#005DBA; color:#FFFFFF`
- Editorial / dark mode: `background:#FFFFFF; color:#231F20`

Verify the skip link (Tab on page load) renders legibly in every theme and mode.

### 4.6 Hard-coded literals outside the token blocks

Map by **role** using the audit from §3, and prefer `var(--…)` over a new literal:

| Old family | Role → new |
|---|---|
| Cream family (`#F7F2EB #F7F2E9 #F4EEE3 #F3EDE4 #F0EBE3 #EEE6D9 #EDE7DC #EAE1D2 #E3D9C9 #E0D5C4 #DFD4C3 #F9F7F4`, `rgba(245,240,232,…) rgba(247,242,235,…) rgba(240,235,227,…)`) | surface → `var(--bg)` / `var(--bg-alt)` / `var(--bg-card)`; light text or overlay on dark → `#FFFFFF` / `rgba(255,255,255,α)` |
| Warm ink family (`#1A1714 #1A1510 #1A1210 #0E0C0A #0C0804 #0F0A04 #2A211A #2A1C10 #231F1B #1C1815`, `rgba(26,23,20,…) rgba(12,8,4,…)`) | ink / light-theme overlay / dark-mode surface → `#231F20` / `rgba(35,31,32,α)`; editorial section surface or hero overlay → `#6A3277` / `rgba(106,50,119,α)` (the 55× inline `#0E0C0A` on product/journal image wells and the `.hero` / `.shop-hero` gradients are candidates — decide each by context in the audit) |
| Bronze family (`#C4956A #D4A87A #8B6442 #8B5F37 #A07040 #54351A #E8D5B8 #D4C4B0 #C4A88C #B09070 #D9A066 #E8C090`, `rgba(196,149,106,…) rgba(212,168,122,…)`) | text on light → `var(--accent-text)`; border → `var(--border)` / `var(--border-strong)`; icon / rule / hover / surface → `var(--accent)`; pressed → `var(--accent-deep)` |
| Terracotta `#8B3A2A` | script → `var(--script)` |
| Clinical blue (`#2A4A8A #3D6DB5 #1E3A6A`, `rgba(42,74,138,…)`) | → `#005DBA` / `rgba(0,93,186,α)` |
| "Free-from" greens (`rgba(100,180,100,…) rgba(100,200,140,…) rgba(80,160,100,…)`) | → `rgba(0,69,57,α)` — start from existing alpha, verify, adjust per §2.2 alpha rule |
| Error red `rgba(204,34,34,…)` | → `rgba(215,40,47,α)` — start from existing alpha, verify visibility, adjust per §2.2 alpha rule |
| `#000` / `rgba(0,0,0,…)` in shadows | keep (documented exception; log each) |

### 4.7 Assets and meta

- `assets/kotiva-logo.svg` fill `#231f20` — keep. `assets/favicon.svg` fill `#231f20` — keep.
- `assets/kotiva-logo-white.svg` fill `#F7F2EB` → `#FFFFFF`.
- Regenerate `favicon-48.png` / `apple-touch-icon.png` (`scripts/generate-icons.py`) **only if** they carry a cream background.
- `<meta name="theme-color">` on every page → `#FFFFFF`; dark variant, if present → `#231F20`. Verify whether the existing theme-color mechanism follows the site's manual `data-mode` toggle (mode is set via `localStorage`, not `prefers-color-scheme`). If it does not, and correcting it would require new JavaScript behaviour, leave the behaviour unchanged and document it as outside the colour-only scope. Do not introduce a theme-color synchronisation system.
- Do not edit any raster photo, product image or hero image.
- `updateNavLogo()` in `js/layout.js` switches logo variants by surface. The variant is selected by the actual rendered surface: black logo on approved light surfaces, white logo on approved dark surfaces — for the current palette that means black on white/light-blue tints and white on purple/`#231F20`. Do not create a new logo variant. Verify on the home hero, shop hero and inner pages in both modes; change the function only if a surface condition is genuinely wrong, and only that condition.

### 4.8 Product-category indicators — remap only, no new behaviour

Do not introduce new category-colour behaviour. Full product-card differentiation belongs to **Amendment 2** and is outside this task. Do not add category-coloured components, backgrounds, cards, sections or treatments.

The audit is **expected** to find two existing category-indicator patterns: the concern/category pill on product cards and the product page, and the shop filter pills. Verify this against the repository before editing. If the repository differs, document the actual patterns and do not introduce new behaviour.

Existing indicators may be remapped to their approved colours, keyed off the product's actual `category` / `concern` data (`js/data-lite.js`, `data-tags`), not off product-type assumptions:

| Data value | Guideline line | Text | Border / surface tint |
|---|---|---|---|
| `category: Cleanser` | Cleansing | `#004539` | `#8AB7E9` @ 0.15 |
| `category: Toner` | Toner | `#6A3277` (accessible substitute) | `#D39DD8` @ 0.15 |
| `category: SPF` | Sunscreen | `#231F20` (orange fails as small text) | `#EC7725` border, @ 0.15 surface |
| `category: Hand Care` | Hand Care | `#005DBA` | `#005DBA` @ 0.15 |
| `category: Hair` | Hair | `#6A3277` | `#6A3277` @ 0.15 |
| `concern: Brightening` | Whitening | `#005DBA` (accessible substitute) | `#C0CEDB` @ 0.40 |
| `category: Treatment`, `Serums`, `Body`, `Lip Care` | **no approved line colour** | neutral: current pill treatment with `var(--border-strong)` and `var(--fg)` | — |

The Brightening → Whitening mapping is applied because every `concern: Brightening` product in `js/data-lite.js` (KOT012, 014, 016, 017, 018, 019) is a "Whitening …" SKU. Re-verify this against the product data before applying; if Brightening turns out to be a concern shared across unrelated products, do not treat it as the Whitening brand category.

Skin Repair (`#D7282F`) has no confirmed category in the current data. Do **not** map Treatment/Serums/Acne to it by assumption. If the client confirms which SKUs are the Skin Repair line, add that mapping then and log it.

**Shop filter pills must not gain new category-specific behaviour in this PR.** If the filters already resolve category-specific colours from existing data, remap those colours per the table above. Otherwise preserve the existing single active-state behaviour using the new semantic accent tokens. Inactive state unchanged.

**Category-colour precedence** (one colour per indicator):
1. Explicit approved product-line/category mapping (`category` column above)
2. Confirmed concern mapping (`concern: Brightening` → Whitening)
3. Neutral fallback

Never combine two category colours on one existing indicator unless the current UI already represents multiple categories. Two SKUs satisfy both rule 1 and rule 2 — KOT012 *Whitening Hand Cream* (Hand Care) and KOT019 *Whitening Wash Gel* (Cleanser); apply rule 1 and list both in `docs/COLOUR-MIGRATION.md` for client confirmation of which line they belong to.

---

## 5. Files in scope

- `css/kotiva.css`
- Every root `*.html` and `product/*.html` (inline styles, `<style>` blocks, `theme-color` meta)
- `scripts/generate-product-pages.js`, `scripts/generate-ingredients-page.js`
- `js/animations.js`, `js/layout.js` — only if they contain colour literals or the logo-surface condition needs correcting
- `assets/kotiva-logo-white.svg`, favicons per §4.7
- `package.json` — only to register the migration validation/audit scripts; no dependency or unrelated script changes
- **Do not touch:** `IMAGE-PROMPTS.md`, `README.md`, `llms.txt`, `data/`, `js/data-*.js`, `js/routine-*.js`, any raster asset

**Generated-file ownership:** if a generated HTML file (`product/*.html`, `ingredients.html`) contains a colour that originates from a generator, fix the generator source first and regenerate. Do not treat direct edits to generated output as the source of truth — the next regeneration would revert them, and `scripts/check-consistency.js` (generated-drift) will fail.

`kotiva.css`, `layout.js`, `animations.js` carry `?v=` cache-bust tokens. **Bump the token for every modified asset in every reference in one pass** (all HTML + both generators). `check-consistency.js` fails otherwise.

### Allowed non-colour changes (exhaustive)

- cache-bust version tokens
- migration documentation (`docs/`)
- audit / screenshot / contrast / palette-validation scripts (`scripts/`) and their registration in `package.json`
- colour-related CSS / SVG / meta values
- generator colour templates
- JS colour literals, or the logo-surface condition in `updateNavLogo()` only where required

Anything outside this list is a regression.

**No auto-fix outside scope:** if `npm test`, Playwright or any audit reveals a pre-existing failure unrelated to this colour migration, do not fix it in this PR. Record it separately in `docs/COLOUR-MIGRATION.md` and continue only if it does not prevent colour verification.

---

## 6. Method

1. Branch `feat/brand-colours`. Capture reference screenshots with a Playwright script (`scripts/screenshot-pages.mjs`; Playwright is already a devDependency). **Matrix, for every page family** (home, shop, one product page, about, science, routine-finder, journal, contact, ingredients, privacy, terms): desktop-light, desktop-dark, 390px-light, 390px-dark. For pages containing editorial or clinical sections, capture those sections in every cell of the matrix (full-page screenshots, not viewport-only).
2. Run the §3 audit and write `docs/COLOUR-MIGRATION.md`.
3. Add the `--k-*` tokens; rewrite the four token blocks (§4.1–4.4) and the skip link (§4.5).
4. Work through the audit table, replacing each literal by role (§4.6). Update the doc as you go. Fix generators before generated output.
5. Apply §4.7 and §4.8.
6. Regenerate product/ingredients pages; bump `?v=`; run `npm test`.
7. Re-capture the full screenshot matrix (keep both sets outside git unless policy requires them; write the comparison report into `docs/colour-migration/`); compare pair by pair. Differences may be **colour only**. Any change in position, size, spacing, font, wrapping, visibility or imagery is a regression — fix it. Do not interpret sub-pixel anti-aliasing or browser rasterisation noise as a layout regression; determine structural regression from geometry, wrapping, visibility, imagery and DOM/layout measurements in addition to the visual diff.
8. **Programmatic WCAG audit**: write `scripts/contrast-audit.mjs` that composites every rgba surface over its actual parent and computes contrast for every text/surface pair and every functionally necessary UI component boundary, icon or state. Require ≥4.5:1 for normal text, ≥3:1 for large text (WCAG 18pt regular / 14pt bold ≈ ≥24px regular / ≥18.66px bold; use ≥19px bold as the implementation-safe threshold), and ≥3:1 for non-text UI information required to identify a control, state or meaningful graphic. Decorative borders and separators are **not** automatically subject to the 3:1 threshold, but must be inspected for adequate visibility — do not change subtle decorative borders to chase 3:1. For text rendered over raster photographs (editorial heroes), CSS compositing alone is not sufficient: sample representative rendered pixels/regions at the text location or inspect the final screenshot there, and do not claim a ratio based only on the overlay colour while a photograph remains visible beneath it. Output a table into `docs/COLOUR-MIGRATION.md`. Any failure → change the role/token or the alpha per §2.2; never the base colour.
9. **Computed-style verification**: for every theme × mode combination, use Playwright to read computed `background-color`, `color` and `border-color` for at least: `body`, `.nav` (transparent and scrolled), `.btn-primary`, `.btn-secondary` (or the secondary button class in use), script text, a card, a filter pill (active and inactive), a category indicator, the footer, and `.skip-link` (focused). Also audit `::before` / `::after` pseudo-elements that render visible colour (rules, icons, overlays, badges, decorative blocks) via `getComputedStyle(el, '::before')` / `'::after'`. Exercise existing `:hover`, `:focus-visible`, `:active`, `:disabled`, form-success and form-error states where they exist — old bronze values commonly survive only in interaction rules. For keyboard focus, verify the complete rendered indicator (outline/border/box-shadow combination) stays visually distinguishable against adjacent surfaces; do not remove or weaken an existing focus indicator for palette uniformity. Assert every value resolves to an approved base colour or approved alpha. Output the table into `docs/COLOUR-MIGRATION.md`.
9b. **Mode initialisation**: the site persists mode in `localStorage` (`kotiva-mode`) and applies it pre-paint in `layout.js`. Verify initial render with light and dark persisted mode (and with `prefers-color-scheme` if the code already honours it): no old cream/brown surface may flash before the theme/mode attributes apply. Do not introduce new theme behaviour.
10. **Token and cascade check**: confirm every `--k-*` token either (a) has an intentional current use or (b) is retained as an official palette token with its currently-unused status documented — do not invent UI usage to make a token appear in computed styles (`#D7282F` Skin Repair and `#EC7725` Sunscreen may legitimately be unused outside their listed roles); confirm no later rule in `kotiva.css` silently restores an old colour; resolve CSS custom-property dependency chains for every colour-bearing token (`--foo: var(--bronze)`, `--bar: var(--foo)`) so that no semantic alias ultimately resolves to an old or unapproved colour even when the alias itself contains no literal — validate both static dependency resolution and final computed resolution in each theme/mode, since a `var(--x, <fallback>)` fallback may only become active in one cascade state; confirm theme specificity/order does not leak Ritual values into Editorial, Clinical or Dark mode; confirm the three `dark-mode × data-theme` combinations explicitly.
11. Verify interactively: light/dark toggle; nav transparent→scrolled (correct logo variant in every state); filter pills; accordions; routine-finder quiz; contact form success/error; skip link in all themes/modes.

---

## 7. Gates before reporting done

- `npm test` green (routine engine, consistency gate incl. version tokens and generated drift, global header/footer consistency, routine-model sync, and the new palette validator).
- **Known old-palette grep (secondary diagnostic — not the authoritative completeness check; `validate-palette.mjs` is)** returns nothing outside `IMAGE-PROMPTS.md` / `README.md`:
  `grep -rniE '#F7F2EB|#C4956A|#1A1714|#8B6442|#8B5F37|#D4A87A|#8B3A2A|#2A4A8A|#0E0C0A|#1C1815|#1A1510|196,149,106|212,168,122|26,23,20|12,8,4|245,240,232|247,242,235|240,235,227|100,180,100|100,200,140|204,34,34' css js *.html product scripts assets`
- **Palette validation script (authoritative)**: write `scripts/validate-palette.mjs` that scans `css/`, `js/`, `scripts/`, `assets/` (SVG and text), root `*.html` and `product/*.html` — generated output **and** generator templates — parses every colour literal (3-, 4-, 6- and 8-digit HEX; `rgb()`/`rgba()`/`hsl()`/`hsla()` in both legacy comma syntax and modern space/slash-alpha/percentage syntax — prefer a real CSS value parser over regex where practical), normalises case, whitespace and decimals so that `#fff`, `#FFF`, `#FFFFFF`, `rgb(255,255,255)`, `rgb(255 255 255)` are one value and `rgba(255,255,255,.18)` = `rgb(255 255 255 / 18%)`, resolves each to an RGB triplet plus alpha, and fails if any base RGB is outside the allowlist `#FFFFFF #231F20 #8AB7E9 #005DBA #6A3277 #D7282F #D39DD8 #004539 #EC7725 #C0CEDB`, except `0,0,0` in the documented retained shadows. The validator checks **both** the base RGB against the allowlist **and** the alpha against the approved combinations and controlled families in §2.2, using the audited alpha baseline from §3 for the shadow/overlay/status families — an approved RGB with an arbitrary alpha (e.g. `rgba(138,183,233,0.333)`) is a failure unless it is a documented existing overlay/shadow exception. It validates **source literals only**; it must not reject browser-composited effective colours (e.g. `rgba(138,183,233,0.18)` over white). Scan scope includes `var()` fallback arguments; literals inside `linear-gradient()`, `radial-gradient()`, `conic-gradient()`, `box-shadow`, `text-shadow`, `filter: drop-shadow()`; and SVG paint attributes `fill`, `stroke`, `stop-color`, `flood-color`, `lighting-color`. Keywords that introduce no RGB value (`transparent`, `currentColor`, `inherit`, `initial`, `unset`) are permitted where already semantically appropriate; if existing `@media (forced-colors: active)` / high-contrast rules use CSS system colours (`Canvas`, `CanvasText`, `ButtonText`, `Highlight`, …), preserve them as an accessibility exception, document them, and never force brand colours into forced-colors mode; named colours (`white`, `black`, `red`, …) are normalised and must be replaced with the approved token/literal. Passing generated HTML while leaving an old or unapproved colour in a generator is a failure. Add it to `npm test`.
- **HEX inventory** (secondary diagnostic) returns only the ten approved base colours plus the documented `#000`/`#000000` shadow exception:
  `grep -rhoiE '#[0-9a-f]{3,6}\b' css js scripts assets *.html product | tr a-f A-F | sort -u`
- **RGB/HSL inventory** (secondary diagnostic): every RGB base is one of `255,255,255` · `35,31,32` · `138,183,233` · `0,93,186` · `106,50,119` · `215,40,47` · `211,157,216` · `0,69,57` · `236,119,37` · `192,206,219`, plus `0,0,0` only for documented existing shadows. No `hsl`/`hsla` values may remain unless explicitly documented:
  `grep -rhoiE '(rgba?|hsla?)\([^)]*\)' css js scripts assets *.html product | tr -d ' ' | sort -u`
- **Every `--script` declaration** resolves to `#D7282F`: `grep -n -- '--script' css/kotiva.css`.
- Every page loads in both modes with no console errors and no 404 assets.
- **Zero-non-colour-diff gate**: inspect `git diff` excluding generated output and `docs/`. Generated HTML is excluded from this **manual source-level** review only because its changes mirror generator changes; it remains fully subject to generated-drift checks, palette validation, old-palette diagnostics, screenshot regression, and an HTML structure/content equivalence check against its pre-migration version (diff the regenerated file against the original with colour values masked — the result must be empty). Regeneration must not introduce structural or content changes merely because the file is generated. No HTML structure, copy, class names, DOM order, dimensions, spacing, typography, animation parameters or JavaScript behaviour may change; only the items in the §5 allowed list. `git diff --stat` shows changes only in §5 files (incl. `package.json` script registration) plus the new `docs/` and scripts. Revert anything else.
- Full screenshot matrix (before/after) generated as local/CI audit artifacts; commit only the comparison report and representative evidence under `docs/colour-migration/` unless repository policy explicitly requires all baseline images.
- Programmatic contrast audit and computed-style verification complete. All mandatory accessibility rows must pass. Any brand-vs-accessibility exception explicitly permitted by this brief (e.g. the script-on-purple rule in §4.2) must be marked **BLOCKED — DESIGNER SIGN-OFF**, never PASS, and must appear in the final sign-off list. Do not classify an unresolved exception as passing.
- All ambiguous occurrences (§1) resolved or explicitly reported.

---

## 8. Decisions recorded for designer sign-off (list in `docs/COLOUR-MIGRATION.md`)

1. Alpha variants of approved colours (§2.2) are used for surfaces, secondary text, borders and overlays because the palette defines no neutrals; fallback stated.
2. Editorial/dark sections use purple `#6A3277` per the Amendment 0 mockup; dark **mode** uses black `#231F20` (the mockup shows no dark mode).
3. `#005DBA` carries accent text on light surfaces. On dark surfaces, `#8AB7E9` may carry accent text where programmatic WCAG verification passes.
4. Primary buttons on purple are white-fill/black-text per the mockup; on light surfaces they keep their shape with `--accent` fill and `#231F20` text.
5. Toner and Whitening use `#6A3277` / `#005DBA` as accessible text substitutes without redefining the category colours.
6. Cleanser indicators combine the two approved Cleansing colours: `#004539` for accessible text and `#8AB7E9` for the light category surface. The guideline provides both as Cleansing colours but does not define their UI relationship.
7. Treatment, Serums, Body and Lip Care have no approved line colour and stay neutral; Skin Repair mapping awaits client confirmation.
8. Skip link is theme-explicit (`#005DBA`/white on light; white/`#231F20` on dark).
9. `--accent-deep` retained as a legacy token whose resolved value is theme-dependent (white on dark surfaces).
10. `#000` retained in existing shadows.
11. Shop filter pills keep single accent active-state behaviour unless category colouring already exists; KOT012 and KOT019 line membership awaits client confirmation.

---

## 9. Final report

When every gate passes, return:

1. Summary of the colour migration completed
2. Files changed
3. Old-palette occurrences by family, as counts: before · migrated · intentionally retained · unresolved · after (e.g. Bronze — before 87, migrated 87, retained 0, unresolved 0, after 0)
4. Official palette / token mapping implemented (by theme and mode)
5. Accessibility audit result (with the photo-text note where applicable)
6. Palette validator result
7. Screenshot regression result
8. Generated-page consistency result
9. Unresolved / ambiguous occurrences
10. Designer / client sign-off items (§8 list plus anything logged)
11. Pre-existing failures found and left untouched
12. Confirmation that no non-colour UI/behaviour changes were made
13. Git report: starting commit SHA, starting dirty files, files modified by the migration, newly generated files, final `git status`, and "unexpected files changed: 0" (a clean worktree is not required if unrelated local changes pre-existed)

Do not report "done" while any mandatory implementation/test gate fails. Designer-sign-off items explicitly permitted by this brief may remain **OPEN FOR SIGN-OFF**, but must be listed separately from implementation failures.

If this brief conflicts with the code, keep the code's **behaviour and structure** and apply this brief's **colours**. Do not stop to ask unless a mapping is impossible without a structural change — then leave that spot unchanged and document it.
