# KOTIVA — Brand Colour Migration (Amendment 0)

Brief: `KOTIVA-COLOR-PALETTE-PROMPT-FINAL.md`. Colour-only migration from the warm cream/bronze system to the
approved KOTIVA palette. This document is written in two stages: **Part A (pre-edit audit)** was committed to
disk before any colour value was changed; **Part B (results)** is appended after implementation.

---

## Part A — Pre-edit audit (brief §3)

### A.1 Git state before the task (§3.7)

| Item | Value |
|---|---|
| Starting branch | `change-identity` (up to date with `origin/change-identity`) |
| Starting commit | `28bc619cd75548f7b70c48e869a51e75d89d36ae` ("chenge identity prompt") |
| `git status --short` at start | *(empty — clean worktree, no pre-existing user changes)* |
| Migration branch | `feat/brand-colours`, created from `28bc619` |

Because the worktree was clean, every change in the final diff belongs to this migration.

### A.2 Audit tooling

| Script | Purpose |
|---|---|
| `scripts/lib/colour-literals.mjs` | One shared parser for colour literals (3/4/6/8-digit HEX incl. `%23`-encoded HEX in data URIs, `rgb/rgba/hsl/hsla` in comma and space/slash syntax, named/system colours inside colour-bearing declarations, SVG paint attributes and JS style assignments, RGB(A) tuples in the Python icon script) + WCAG luminance/contrast/compositing helpers. |
| `scripts/colour-inventory.mjs` | Full inventory → `docs/colour-migration/inventory-before.json` (every occurrence: file, line, raw value, normalised value, selector/element context, property, in-comment / in-gradient / in-shadow flags, family, generator-owned flag). |
| `scripts/screenshot-pages.mjs` | Full-page screenshot + per-element geometry matrix (11 page families × desktop/390px × light/dark = 44 captures). Baseline captured in `scratch/colour-migration/before/` (git-ignored) **before any edit**: 44/44 captured, 0 console errors, 0 failed requests. |
| `docs/colour-migration/old-palette-families.json` | Pre-migration colour families (kept under `docs/` so no file in `scripts/` carries an old-palette literal). |
| `docs/colour-migration/alpha-baseline.json` | Audited pre-migration alpha baseline (§3.6). |

### A.3 Colour custom properties inventory (§3.1)

| Block | Selector | Colour tokens |
|---|---|---|
| Legacy aliases | `:root` | `--off-white #F7F2EB`, `--black #1A1714`, `--bronze #C4956A`, `--cream #F7F2EB`, `--muted rgba(26,23,20,.55)`, `--border-color rgba(196,149,106,.20)` |
| Ritual (default) | `[data-theme="ritual"], html` | `--bg #F7F2EB`, `--bg-alt #EDE7DC`, `--bg-card #F3EDE4`, `--fg #1A1714`, `--fg-mid #5A5550`, `--fg-dim #6D6760`, `--accent #C4956A`, `--accent-light #D4A87A`, `--accent-deep #8B6442`, `--accent-text #8B5F37`, `--border rgba(196,149,106,.20)`, `--border-strong rgba(196,149,106,.50)`, `--script #8B3A2A`, `--shadow`/`--shadow-hover rgba(26,23,20,.08/.14)` |
| Editorial | `[data-theme="editorial"]` | `--bg #1C1815`, `--bg-alt #231F1B`, `--bg-card #2A2520`, `--fg #F7F2EB`, `--fg-mid/dim rgba(247,242,235,.70/.55)`, `--accent #D4A87A`, `--accent-light #E8C090`, `--accent-deep #C4956A`, `--accent-text #D4A87A`, `--border/-strong rgba(212,168,122,.20/.50)`, `--script #C4956A`, `--shadow/-hover rgba(0,0,0,.30/.45)` |
| Clinical | `[data-theme="clinical"]` | `--bg #FFFFFF`, `--bg-alt #F5F7F8`, `--bg-card #F0F2F4`, `--fg #0D1117`, `--fg-mid #4A5260`, `--fg-dim #63676F`, `--accent #2A4A8A`, `--accent-light #3D6DB5`, `--accent-deep #1E3A6A`, `--accent-text #2A4A8A`, `--border/-strong rgba(42,74,138,.15/.40)`, `--script #2A4A8A`, `--shadow/-hover rgba(13,17,23,.06/.12)` |
| Dark mode | `html[data-mode="dark"], html[data-mode="dark"] [data-theme="ritual"]` | `--bg #1A1510`, `--bg-alt #211A14`, `--bg-card #261E18`, `--fg #F0EBE3`, `--fg-mid/dim rgba(240,235,227,.72/.50)`, `--border/-strong rgba(196,149,106,.22/.50)`, `--script #D9A066` (accent tokens inherited from Ritual) |
| Var fallbacks | `kotiva.css` `.pin-rail-progress span.is-active` | `var(--accent-light, #C4956A)` |
| Var fallbacks | `journal.html` sticky filter JS | `var(--bg,#0d0d0d)` |

**Undefined tokens referenced by existing code** (pre-existing, not colour literals, left untouched): `--bronze-dim`
(contact.html `.contact-info-value a:hover`), `--taupe` (about.html inline styles), `--border-light`
(routine-finder.html `.result-step`, superseded by a later rule), `--ease-out`, `--gutter`. The class
`btn-outline-dark` (about.html) has no CSS definition.

**Theme usage in markup:** `ritual` and `editorial` sections are used; **no element uses `data-theme="clinical"`**
(its tokens are still migrated and verified with a test fixture).

### A.4 Other colour-bearing properties (§3.3)

| Property | Occurrences | Decision |
|---|---|---|
| `color-scheme` | contact.html L128–129 (`select.form-field` light/dark) | keep — keyword, carries no RGB |
| `accent-color`, `caret-color`, `outline-color`, `text-decoration-color`, `column-rule-color` | none | nothing to change (not added) |
| `outline` shorthands with colour | kotiva.css `.footer-brand-link:focus-visible`, `.ritual-item:focus-visible`; contact.html `.faq-q:focus-visible`; journal.html newsletter error outline (JS) | mapped by role below |
| `@media (forced-colors)` / system colours | none | — |
| Named colours (`white`, `black`, …) | none found | — |
| `<meta name="theme-color">` | **none on any page or generator** | nothing to change; not added (would be a markup change) |

### A.5 Literal inventory summary (§3.2)

456 colour-literal occurrences before migration (full list: `docs/colour-migration/inventory-before.json`).

| Family | Total | In code | In comments | In generated output (product/*.html, ingredients.html) |
|---|---|---|---|---|
| Warm ink | 149 | 122 | 27 | 50 |
| Cream | 120 | 112 | 8 | 25 |
| Bronze | 90 | 82 | 8 | 0 |
| Free-from green | 58 | 58 | 0 | 50 |
| Clinical | 14 | 14 | 0 | 0 |
| Error red | 5 | 5 | 0 | 0 |
| Black (`rgba(0,0,0,…)`) | 3 | 3 | 0 | 0 |
| Warm neutral text | 2 | 2 | 0 | 0 |
| Terracotta | 2 | 1 | 1 | 0 |
| Unlisted (comment-only historical values `#706A62`, `#8A9099`) | 2 | 0 | 2 | 0 |
| Already approved (`#231f20`, `#fff`, `#FFFFFF`, white inset highlights) | 11 | 9 | 2 | 0 |

Every literal in `product/*.html` (25 files × 5 code + 1 comment) **originates from
`scripts/generate-product-pages.js`** (generator-owned = true for all 150). `ingredients.html` carries no colour
literal. Generated files are fixed through the generators and regenerated, never edited directly.

Comments that quote an old-palette value (historical contrast measurements) are rewritten to state the migrated
value, because the old-palette grep gate covers comments too. No comment without a colour literal is touched.

### A.6 Role classification and proposed targets (§3.4, §3.9)

Mapping is by **role**, never by HEX family. `G` = in a gradient, `S` = shadow. "gen" = generator-owned.
Dark wells rule used throughout: a dark panel **inside `data-theme="editorial"`** becomes the editorial surface
(`var(--bg)` → `#6A3277`); a dark panel **outside** an editorial theme is a neutral dark well (`#231F20`).

#### css/kotiva.css — tokens

| Line | Token / selector | Old | Role | Target |
|---|---|---|---|---|
| 36–62 | `:root` new palette | — | official palette tokens | add `--k-white #FFFFFF`, `--k-black #231F20`, `--k-blue-light #8AB7E9`, `--k-blue #005DBA`, `--k-purple #6A3277`, `--k-red #D7282F`, `--k-lilac #D39DD8`, `--k-green #004539`, `--k-orange #EC7725`, `--k-grey-blue #C0CEDB` |
| 54, 57 | `--off-white`, `--cream` | `#F7F2EB` | light surface / light text on dark | `#FFFFFF` |
| 55 | `--black` | `#1A1714` | ink / dark panel | `#231F20` |
| 56 | `--bronze` | `#C4956A` | accent (rules, fills, bullets, borders) | `#8AB7E9` |
| 58 | `--muted` | `rgba(26,23,20,.55)` | dim text | `rgba(35,31,32,0.62)` |
| 59 | `--border-color` | `rgba(196,149,106,.20)` | border | `rgba(35,31,32,0.12)` |
| 89–112 | Ritual block | see A.3 | per §4.1 | `#FFFFFF`, `rgba(138,183,233,0.18)`, `rgba(138,183,233,0.10)`, `#231F20`, `rgba(35,31,32,0.72)`, `rgba(35,31,32,0.62)`, `#8AB7E9`, `#8AB7E9`, `#005DBA`, `#005DBA`, `rgba(35,31,32,0.12)`, `rgba(35,31,32,0.30)`, `#D7282F`, shadows `rgba(35,31,32,.08/.14)` |
| 115–134 | Editorial block | see A.3 | per §4.2 | `#6A3277`, `#6A3277`, `rgba(255,255,255,0.07)`, `#FFFFFF`, `rgba(255,255,255,0.78/0.64)`, `#8AB7E9`, `#8AB7E9`, `--accent-deep #FFFFFF`, `--accent-text` `#8AB7E9` **only if it verifies — see A.9**, borders `rgba(255,255,255,0.18/0.40)`, `--script #D7282F`, shadows keep `rgba(0,0,0,.30/.45)` |
| 137–153 | Clinical block | see A.3 | per §4.3 | `#FFFFFF`, `rgba(138,183,233,0.18)`, `rgba(138,183,233,0.10)`, `#231F20`, `rgba(35,31,32,0.72/0.62)`, all four accents `#005DBA`, borders `rgba(0,93,186,0.15/0.40)`, `--script #D7282F`, shadows `rgba(35,31,32,.06/.12)` |
| 1640–1651 | Dark mode block | see A.3 | per §4.4 | `#231F20`, `rgba(255,255,255,0.04)`, `rgba(255,255,255,0.07)`, `#FFFFFF`, `rgba(255,255,255,0.78/0.64)`, **add** `--accent`/`--accent-light #8AB7E9`, `--accent-text #8AB7E9` (verify vs `#231F20`), `--accent-deep #FFFFFF`, borders `rgba(255,255,255,0.18/0.40)`, `--script #D7282F` |

#### css/kotiva.css — literals outside the token blocks

| Line | Selector | Property | Old | Role | Target |
|---|---|---|---|---|---|
| 70, 76 | `.skip-link` | background / color | `var(--accent)` / `#1A1714` | skip link (§4.5) | `#005DBA` / `#FFFFFF`; add `[data-theme="editorial"] .skip-link, html[data-mode="dark"] .skip-link { background:#FFFFFF; color:#231F20 }` |
| 246 | `.btn-gold` | color | `#1A1714` | text on accent fill | `#231F20` |
| 249 | `[data-theme="clinical"] .btn-gold` | color | `#F7F2EB` | text on blue fill | `#FFFFFF` |
| 250 | `.btn-gold:hover` | (color) | inherited `#231F20` on `--accent-deep` | hover text on pressed fill | add `color: var(--bg)` (white on `#005DBA` light; `#231F20` on white dark) |
| new | `[data-theme="editorial"] .btn-gold` (+`:hover`) | bg / color / border | — | primary button on purple (§4.2) | `#FFFFFF` / `#231F20` / `#FFFFFF`; hover `var(--accent)` / `#231F20` |
| 258 | `.btn-outline-light` | border | `rgba(247,242,235,.5)` | control border on dark | `rgba(255,255,255,0.40)` |
| 293 | `.nav.scrolled` | background | `rgba(247,242,235,.96)` | light sticky surface | `var(--bg)` (no approved translucent white) |
| 295 | `.nav.scrolled` | box-shadow S | `rgba(26,23,20,.06)` | shadow | `rgba(35,31,32,0.06)` |
| 319 / 321 | `.nav-logo-text` / `.light` | color | `#1A1714` / `#F7F2EB` | ink / light text | `#231F20` / `#FFFFFF` |
| 335 | `.nav-link` | color | `rgba(247,242,235,.9)` | nav text over dark hero | `#FFFFFF` |
| 346–347, 1620–1622 | `.nav-theme-btn` | border / color | `rgba(247,242,235,.3/.35)` / `#F7F2EB` | control border / icon on dark | `rgba(255,255,255,0.40)` / `#FFFFFF` |
| 368 | `.nav-hamburger span` | background | `#F7F2EB` | icon on dark | `#FFFFFF` |
| 415–418 G | `.hero-bg` | background | bronze glows `.50/.40`, ink `.70`, stops `#0C0804 #1C1008 #2C1A0C #1A1008` | editorial hero placeholder | `rgba(106,50,119,α)` same alphas; stops `#6A3277` |
| 424 | `.hero-bg` | color | `rgba(247,242,235,.12)` | ghost placeholder text | `rgba(255,255,255,0.07)` |
| 432 G | `.hero-overlay` | background | `rgba(26,23,20,.85/.3/.1)` | editorial photo overlay | `rgba(106,50,119,α)` same alphas |
| 459 | `.hero-title` | color | `#F7F2EB` | display text on photo | `#FFFFFF` |
| 462, 1968 S | `.hero-title` | text-shadow | `rgba(26,23,20,.55/.35)`, `rgba(12,8,4,.75/.55)` | legibility shadow | `rgba(35,31,32,α)` same alphas |
| 467 / 1971 S | `.hero-sub` | color / text-shadow | `rgba(247,242,235,.75)` / `rgba(12,8,4,.60)` | mid text on photo / shadow | `rgba(255,255,255,0.78)` / `rgba(35,31,32,0.60)` |
| 482 / 1719 | `.hero-scroll` | color / background | `rgba(247,242,235,.5)` / `.10` | decorative icon / chip on dark | `rgba(255,255,255,0.64)` / `rgba(255,255,255,0.07)` |
| 584 G | `.category-card-overlay` | background | `rgba(26,23,20,.75)` | ink scrim over photo in a **ritual** section | `rgba(35,31,32,0.75)` — documented existing-overlay exception (sign-off) |
| 590, 604, 613, 615 | `.category-card-info/-count/-arrow` | color / border | `#F7F2EB`, `rgba(247,242,235,.75/.3)` | light text / border on photo | `#FFFFFF`, `rgba(255,255,255,0.78)`, `rgba(255,255,255,0.40)` |
| 1586 | `.category-card-bg` | color | `rgba(247,242,235,.25)` | ghost placeholder text | `rgba(255,255,255,0.18)` |
| 1996–1997 S | `.category-card-name/-count` | text-shadow | `rgba(12,8,4,.6/.4)` | legibility shadow | `rgba(35,31,32,α)` same alphas |
| 2293 | `.category-card-arrow` | background | `rgba(0,0,0,.35)` | translucent chip on photo (**not** a shadow) | `rgba(35,31,32,0.35)` — documented existing-overlay exception |
| 2296 | `.category-card-arrow` | border-color | `rgba(247,242,235,.6)` | border on dark | `rgba(255,255,255,0.40)` |
| 1565–1582 G | `.category-card[href*=…] .category-card-bg` (6) | background | bronze/brown gradients | placeholder surface under category photos (not a category indicator) | `linear-gradient(145deg, var(--bg-alt) …)` — same function and stops, neutral surface token |
| 636, 639 G | `.product-card-img` (+hover) | background | cream radial gradients | light image well for transparent product renders (both modes) | `#FFFFFF` stops |
| 685 | `.badge-doctor` | color | `#1A1714` | text on accent fill | `#231F20` |
| 778 / 1737–1738 G | `.routine-cta-image` | background | `#0C0804`; glow `rgba(212,168,100,.30)`, stops `#120E08 #221808 #180E06` | editorial image placeholder | `#6A3277`; `rgba(106,50,119,0.30)`; stops `#6A3277` |
| 1732–1733 G | `.doctor-image` | background | glow `rgba(196,149,106,.25)`, stops `#1A1210 #2A1C10` | editorial image placeholder | `rgba(106,50,119,0.25)`; stops `#6A3277` |
| 1742–1743 G | `.shop-hero` | background | glow `rgba(196,149,106,.35)`, stops `#0F0A04 #1C1208 #2A1A0C` | editorial hero placeholder | `rgba(106,50,119,0.35)`; stops `#6A3277` |
| 1099, 1113 | `.k-cursor` (+hover) | border / background | `rgba(196,149,106,.55/.07)` | decorative pointer ring | `var(--accent)` / `rgba(138,183,233,0.10)` |
| 1158, 1163 S | `.product-card::after` (+hover) | box-shadow | `rgba(196,149,106,0)` / `.35` | accent hover ring | `transparent` / `rgba(138,183,233,0.25)` |
| 1342, 1425 | `.concern-pill` selected, `.step-dot.active` | color | `#1A1714` | text on accent fill | `#231F20` |
| 1596 | `.filter-sticky` | background | `rgba(247,242,235,.97)` | light sticky surface | `var(--bg)` |
| 1653, 1657 | dark `.nav.scrolled`, `.filter-sticky` | background | `rgba(26,21,16,.97)` | near-opaque dark surface | `rgba(35,31,32,0.97)` |
| 1654 | dark `.nav.scrolled` | border-color | `rgba(196,149,106,.18)` | dark-mode border | `rgba(138,183,233,0.18)` (alpha present in §2.2 for this RGB) |
| 1658 | dark `.filter-sticky` | border-bottom-color | `rgba(196,149,106,.32)` | dark-mode border (alpha not in §2.2) | `var(--border-strong)` — nearest approved semantic border, logged |
| 1663 | dark `.filter-pill` (DEC-159) | border-color | `rgba(196,149,106,.60)` | strong interactive accent border | `rgba(138,183,233,0.60)` |
| 1667 | dark `.filter-pill:hover:not(.active)` | background | `rgba(240,235,227,.07)` | hover surface on dark | `rgba(255,255,255,0.07)` |
| 1673 | dark `.nav.scrolled .nav-theme-btn` | border-color | `rgba(196,149,106,.62)` | strong interactive accent border (DEC-159 equivalent) | `rgba(138,183,233,0.60)` — logged |
| 1677 | dark `.nav-overlay` | background | `#1A1510` | dark surface | `#231F20` |
| new | dark `.filter-pill.active` | color / border | (would inherit white text on white `--accent-deep`) | active pill on dark | add `color: var(--bg); border-color: var(--accent-deep)` |
| 1769–1775 G | `.ritual-tile:nth-child(1–6)`, `.ritual-tile` | background / color | ink→bronze gradients; `rgba(247,242,235,.35)` | editorial image placeholders / ghost text | `linear-gradient(135deg, #6A3277 0%, var(--accent) 100%)`; `rgba(255,255,255,0.40)` |
| 1822–1823, 1838–1839 | `.bs-card` (first definition, superseded) | bg / border | `rgba(247,242,235,.05/.09)`, `rgba(196,149,106,.20/.55)` | card on purple | `rgba(255,255,255,0.04/0.07)`, `rgba(255,255,255,0.18/0.40)` |
| 1851 G | `.bs-card-img` (superseded by `background:none`) | background | cream radial | light plinth | `#FFFFFF` stops |
| 1862, 2504 S | `.bs-card-img img`, `.ritual-item-img img` | drop-shadow | `rgba(58,38,20,.22/.16/.24/.18)` | product silhouette shadow | `rgba(35,31,32,α)` same alphas |
| 1906, 1942 | `.bs-card-vol`, `.bs-card-name` (superseded) | color | `rgba(247,242,235,.55/.90)` | text on dark | `rgba(255,255,255,0.64)`, `#FFFFFF` |
| 2016, 2023 | `.credential-badge` | border / color | `rgba(247,242,235,.25/.75)` | pill border / text on purple | `rgba(255,255,255,0.18)` / `rgba(255,255,255,0.78)` |
| 2034 | `.filter-pill.active` | color | `#F7F2EB` | text on `--accent-deep` fill | `#FFFFFF` |
| 2093, 2094 | dark hamburger / theme-btn | bg / border / color | `rgba(240,235,227,.85/.2/.8)` | icon / border on dark | `rgba(255,255,255,0.78)`, `rgba(255,255,255,0.18)`, `rgba(255,255,255,0.78)` |
| 2483–2484 G | `.ritual-shelf` (unused in markup) | background | cream gradient; inset `rgba(255,255,255,.55)` | light plane | `#FFFFFF` stops; inset highlight kept (existing white shadow exception) |
| 2509, 2514, 2518 | `.ritual-item:focus-visible`, `-name`, `-role` (unused) | outline / color | `#8B5F37`, `#2A211A`, `#8B5F37` | focus / ink / accent text on light | `#005DBA`, `#231F20`, `#005DBA` |
| 2557, 2563 G | `.bs-card` (+hover) | background | cream gradients; inset `rgba(255,255,255,.5)` | light plinth inside purple band | `#FFFFFF` stops; inset highlight kept |
| 2594 | `.bs-card-price` | color | `#54351A` (opacity .78 kept) | price text on white plinth | `#231F20` (accent blue would drop below 4.5:1 at .78 opacity) |
| 2595 | `.bs-card-name` | color | `#2A211A` | ink | `#231F20` |
| 2603, 2604 | `.bs-card-vol`, `.bs-card-cta` | color | `#6B5646`, `#5E4632` | muted text / action text on white | `rgba(35,31,32,0.62)`, `#005DBA` |
| 2608 | `.bs-card .badge-concern` | color / border | `#6B5A45`, `rgba(139,95,55,.28)` | neutral pill on white | `rgba(35,31,32,0.72)`, `rgba(35,31,32,0.30)` |
| 2668, 2672 | `.pin-rail-progress span` (+active fallback) | background | `rgba(247,242,235,.28)`, fallback `#C4956A` | decorative progress dots on purple | `rgba(255,255,255,0.40)`, `var(--accent-light, #8AB7E9)` |
| 2708, 2713 | `.bs-end` (+hover) | background | `rgba(247,242,235,.03/.07)` | panel on purple | `rgba(255,255,255,0.04/0.07)` |
| 2974 S | `.nav.transparent .btn.is-current-cta` | box-shadow | `rgba(26,23,20,.55)` | ring separator (shadow) | `rgba(35,31,32,0.55)` |

#### css/kotiva.css — token-reference role corrections (accent colouring text on a light surface → `--accent-text`; focus → `--accent-deep`)

| Line | Selector | Old | New | Why |
|---|---|---|---|---|
| 256 | `.btn-outline:hover` color | `--accent` | `--accent-text` | text |
| 262 | `.btn-outline-light:hover` color | `--accent` | `--accent-text` | text |
| 340 | `.nav.scrolled .nav-link:hover` | `--accent` | `--accent-text` | text on white bar (transparent-state hover over the photo keeps `--accent`) |
| 399, 2961, 2963 | `.nav-overlay .nav-link:hover / .is-current / .is-current-section` | `--accent` | `--accent-text` | text on overlay surface |
| 1009 | `.footer-links a:hover` | `--accent` | `--accent-text` | text |
| 1250 | `.accordion-trigger:hover` | `--accent` | `--accent-text` | text |
| 1304 | `.breadcrumb a:hover` | `--accent` | `--accent-text` | text |
| 1426 | `.step-dot.done` color | `--accent` | `--accent-text` | text |
| 1433 | `.text-accent` | `--accent` | `--accent-text` | text utility |
| 1554 | `.hero-script-accent` | `--accent-light` | `--script` | handwritten/script typography → red (§1, §2.1) |
| 2026 | `.credential-badge:hover` color | `--accent` | `--accent-text` | text |
| 2173, 2187 | `.pdp-science > summary::after`, `.pdp-science-body a` | `--accent` | `--accent-text` | text glyph / link |
| 2460 | `.routine-notice-clear:hover` color | `--bronze` | `--accent-text` | text |
| 2525 | `.ritual-foot a` | `--accent` | `--accent-text` | text |
| 950, 1387 | `.newsletter-input:focus`, `.form-control:focus` border | `--accent` | `--accent-deep` | sole focus indicator (outline:none); keeps it ≥ old strength |
| 2471 | `.footer-brand-link:focus-visible` outline | `--accent` | `--accent-deep` | focus indicator |

#### Inline `<style>` / `style=""` in root pages

| File:line | Selector / element | Property | Old | Role | Target |
|---|---|---|---|---|---|
| index.html:96–105 G | mobile `.hero-scrim-bottom`, `.hero-overlay` | background | `rgba(12,8,4,.25/.10/.40/.62/.28)` | editorial hero overlay | `rgba(106,50,119,α)` same alphas |
| index.html:110 | `.hero-bg` | background | `#0C0804` | editorial hero ground | `#6A3277` |
| index.html:112–116 G | hero glow + scrims | background | `rgba(196,149,106,.14)`, `rgba(12,8,4,.46/.18/.16/.34/.16/.03)` | editorial hero overlay | `rgba(106,50,119,α)` same alphas |
| index.html:257–317 G | 6 × `.category-card-bg` | background | bronze/cream 2-stop gradients | placeholder surface (overridden by the CSS `!important` rule) | `linear-gradient(145deg,var(--bg-alt),var(--bg-alt))` |
| index.html:362 G | routine CTA scrim | background | `rgba(12,8,4,.55/.15)` | editorial photo overlay | `rgba(106,50,119,α)` |
| index.html:378 G | bestsellers overlay | background | `rgba(196,149,106,.18)`, `rgba(12,8,4,.98/.70/.85)` | editorial photo overlay | `rgba(106,50,119,α)` |
| index.html:406 | "View All 25" `.btn-outline-light` | color / border-color | `rgba(247,242,235,.8/.3)` | text / border on purple | `rgba(255,255,255,0.78)` / `rgba(255,255,255,0.40)` |
| shop.html:76–78 G | shop hero overlays | background | `rgba(15,10,4,.86/.55/.20)`, `rgba(196,149,106,.12)`, `rgba(26,23,20,.6)` | editorial hero overlay | `rgba(106,50,119,α)` same alphas |
| shop.html:81, 82 | hero h1 / p | color | `#F7F2EB`, `rgba(247,242,235,.6)` | text on purple veil | `#FFFFFF`, `rgba(255,255,255,0.64)` |
| about.html:44 | `.about-hero-visual` | background | `#0e0c0a` | dark well **outside** editorial | `#231F20` |
| about.html:55 | `.about-hero-k` | color | `rgba(245,240,232,.03)` | ghost glyph | `rgba(255,255,255,0.04)` |
| about.html:62 G | `.about-hero-quote` | background | `rgba(10,10,10,.9)` | ink scrim over portrait | `rgba(35,31,32,0.9)` — documented existing-overlay exception |
| about.html:78, 111 | comments | — | `#8B6442` | comment | rewritten |
| about.html:196 | `.standard-panel.highlight` | background | `#0e0c0a` | highlighted panel **inside** editorial | `var(--bg)` (`#6A3277`) |
| about.html:240 | `.doctor-badge-sub` | color | `rgba(245,240,232,.49)` | dim text on `#231F20` | `rgba(255,255,255,0.64)` |
| contact.html:70 | `.wtb-desc` | color | `rgba(245,240,232,.49)` | dim text on `#231F20` | `rgba(255,255,255,0.64)` |
| contact.html:130–131 | `select.form-field option` (light/dark) | color / bg | `#1A1714`/`#F7F2EB`; `#F0EBE3`/`#1A1510` | native option list | `#231F20`/`#FFFFFF`; `#FFFFFF`/`#231F20` |
| contact.html:132 | `select.form-field` chevron (data URI) | stroke | `%238B7355` | select affordance icon | `%23005DBA`; dark-mode rule L129 gains the same icon in `%238AB7E9` |
| contact.html:165 | `.faq-q:focus-visible` outline | `var(--bronze)` | focus indicator | `var(--accent-deep)` |
| contact.html:365 | `#cf-success` | border / bg / color | `rgba(80,160,100,.3/.06/.8)` | status (success) | `rgba(0,69,57,0.3)` / `rgba(0,69,57,0.06)` / text: start at `rgba(0,69,57,0.8)`, verify |
| contact.html:366 | `#cf-error` | border / bg / color | `rgba(200,80,80,.3/.06/.85)` | status (error) | `rgba(215,40,47,0.3)` / `rgba(215,40,47,0.06)` / text: start at `rgba(215,40,47,0.85)`, verify |
| journal.html:60, 410–411 | comments | — | `#C4956A`, `#0e0c0a`, `#8B3A2A` | comment | rewritten |
| journal.html:71 | `.jf-visual-wrap` (`data-theme="editorial"`) | background | `#0e0c0a` | feature well inside editorial | `var(--bg)` (`#6A3277`) |
| journal.html:82 | `.jf-visual` | color | `rgba(245,240,232,.04)` | ghost glyph | `rgba(255,255,255,0.04)` |
| journal.html:488, 503 | newsletter input error outline (JS) | outline | `rgba(204,34,34,.4)` | functional error indicator | start at `rgba(215,40,47,0.4)`, verify (§2.2 alpha rule) |
| journal.html:496 | "Subscribed ✓" button (JS) | background | `#2d6a42` | success state | `#004539` (text `#fff` already approved) |
| journal.html:528 | sticky filter JS | `var(--bg, …)` fallback | `#0d0d0d` | surface fallback | `#231F20` |
| product.html:39 | `.pdp-gallery` | background | `#0e0c0a` | dark well outside editorial | `#231F20` |
| product.html:71 | `.pdp-gallery-num` | color | `rgba(245,240,232,.48)` | dim text on dark | `rgba(255,255,255,0.64)` |
| product.html:93 | `.pdp-breadcrumb a:hover` | `var(--bronze)` | text | `var(--accent-text)` |
| product.html:213–214 | `.pdp-free-pill` | border / color | `rgba(100,180,100,.2)`, `rgba(100,200,140,.6)` | "free-from" pill | `rgba(0,69,57,0.2)` / text: start at `rgba(0,69,57,0.6)`, verify |
| routine-finder.html:73, 112, 131, 201, 225 | quiz text (all superseded by the later theme-aware block) | color | `rgba(245,240,232,.4/.4/.35/.3/.3)` | dim text on dark | `rgba(255,255,255,0.64)` |
| routine-finder.html:226 | `.result-retake` (superseded) | border-bottom | `rgba(245,240,232,.15)` | border on dark | `rgba(255,255,255,0.18)` |
| routine-finder.html:96, 100 | `.quiz-option:hover`, `.selected` | background | `rgba(196,149,106,.05/.10)` | hover / selected tint on accent-bordered control | `rgba(138,183,233,0.25)` (table value for this role; `.10` would equal the unselected `--bg-card`) |
| routine-finder.html:116, 246 | `.quiz-option.selected .quiz-option-title` | `var(--bronze)` | text | `var(--accent-text)` |
| routine-finder.html:184 | `.result-step:hover` (superseded) | background | `#111` | hover surface | `var(--bg-alt)` |
| routine-finder.html:266 | `.quiz-rank` color | `var(--bronze)` | text (rank number) | `var(--accent-text)` (border keeps `--bronze`) |
| science.html:210 | `.hero-ing-feature` (`data-theme="editorial"`) | background | `#0D0C0A` | editorial section surface | `var(--bg)` (`#6A3277`) |

#### Generators, assets, scripts

| File:line | Selector / item | Old | Role | Target | Generated output |
|---|---|---|---|---|---|
| generate-product-pages.js:330 | `.pdp-gallery` | `#0e0c0a` | dark well | `#231F20` | product/*.html L30 |
| generate-product-pages.js:333 | comment | `#0e0c0a` | comment | rewritten | L33 |
| generate-product-pages.js:334 | `.pdp-gallery-num` | `rgba(245,240,232,.48)` | dim text on dark | `rgba(255,255,255,0.64)` | L34 |
| generate-product-pages.js:338 | `.pdp-breadcrumb a:hover` | `var(--bronze)` | text | `var(--accent-text)` | L38 |
| generate-product-pages.js:354 | `.pdp-free-pill` | `rgba(100,180,100,.2)`, `rgba(100,200,140,.6)` | free-from pill | as product.html | L54 |
| generate-ingredients-page.js:134 | `.ig-prod:hover` color | `var(--bronze)` | text | `var(--accent-text)` | ingredients.html L41 |
| assets/kotiva-logo-white.svg:6 | `.cls-1, .cls-2` fill | `#F7F2EB` | white logo | `#FFFFFF` | — |
| assets/kotiva-logo.svg, favicon.svg, kotiva-mark-source.svg | fills | `#231f20`, `#fff` | logo black / keyline | keep | — |
| assets/apple-touch-icon.png | opaque plate | `(247,242,235)` measured | icon plate | white plate (§4.7 — it carries the cream background) | — |
| assets/favicon-48.png | transparent ground | — | — | keep (no cream) | — |
| scripts/generate-icons.py:22 | `PLATE` | `(247, 242, 235, 255)` | icon plate | `(255, 255, 255, 255)` | — |

`js/animations.js`, `js/layout.js`: no colour literals. `updateNavLogo()` selects the white logo when the nav is
transparent (over the editorial hero, now a purple veil) or the page is in dark mode (`#231F20`), and the black
logo on the scrolled light bar (`#FFFFFF`) — the conditions are already correct for the new surfaces, so the
function is **not** changed.

### A.7 Product-category indicators (§4.8)

Verified against the repository:

* `.badge-concern` on product cards (shop grid, related products, bestseller rail) renders the product's
  **concern** text in **one** colour for every product (`--accent-deep` text + `--border-strong` border; the rail
  variant hard-codes one brown). No CSS or markup keys its colour off `category` or `concern`.
* The product page shows the category as plain text (`.pdp-category`, `var(--fg-dim)`), not as a pill.
* Shop filter pills (`data-filter` = face/body/serums/treatments/sunscreen/hair) have a single active-state style;
  cards carry `data-tags`, which do not map 1:1 onto `category` (e.g. whitening-day-cream-spf50 is `Treatment` but
  tagged `sunscreen`).

**Conclusion:** no category-coloured indicator exists, so there is nothing to remap. The indicators keep their
existing single treatment on the new semantic tokens; no category colour behaviour is introduced (Amendment 2
territory). The Brightening → Whitening data check still holds (KOT012, 014, 016, 017, 018, 019 are all
"Whitening …" SKUs) and KOT012 / KOT019 remain listed for client confirmation, but the mapping is not applied.

### A.8 Generated-file ownership (§3.5)

All 150 literal occurrences in `product/*.html` originate from `scripts/generate-product-pages.js`;
`ingredients.html` has no literal and one token-reference change from `scripts/generate-ingredients-page.js`.
Both generators are edited first, then re-run; `scripts/check-consistency.js` byte-compares the output.

### A.9 Alpha baseline (§3.6)

Recorded in `docs/colour-migration/alpha-baseline.json` from `inventory-before.json` at `28bc619`:

| Family | Existing alphas before migration |
|---|---|
| Shadows (ink) | .06 .08 .12 .14 .16 .18 .22 .24 .35 .40 .55 .60 .75 |
| Shadows (`rgba(0,0,0)`) | .30 .45 |
| Editorial overlays | .03 .10 .12 .14 .15 .16 .18 .20 .25 .28 .30 .34 .35 .40 .46 .50 .55 .60 .62 .70 .85 .86 .98 |
| Ink overlay exceptions (outside editorial) | .35 .75 .90 |
| White inset-highlight shadows | .50 .55 |
| Status green | .06 .20 .30 .60 .80 1 |
| Status red | .06 .30 .40 .85 1 |

### A.10 Contrast expectations to verify (programmatically, in Part B)

Hand-computed expectations that decide token choices; the audit script is authoritative.

| Pair | Expected | Consequence if confirmed |
|---|---|---|
| `#8AB7E9` text on `#6A3277` | ≈ 4.3:1 | fails 4.5:1 → editorial `--accent-text` cannot be `#8AB7E9`; use `#FFFFFF` (role change, no new colour) |
| `#8AB7E9` text on `#231F20` | ≈ 7.8:1 | dark-mode `--accent-text #8AB7E9` allowed |
| `#D7282F` script on `#6A3277` | ≈ 1.8:1 | fails even 3:1 → **BLOCKED — DESIGNER SIGN-OFF** (script-on-purple rule) |
| `#D7282F` script on `#231F20` | ≈ 3.3:1 | passes only as large text |
| `rgba(35,31,32,.62)` on `--bg-alt` over white | ≈ 4.4:1 | small `--fg-dim` text on `--bg-alt` fails → switch those rules to `--fg-mid` |
| `#231F20` on `#005DBA` | ≈ 2.6:1 | primary-button hover text must switch to `var(--bg)` |

---

## Part B — Results (after implementation)

### B.1 Summary

The warm cream/bronze system is replaced by the KOTIVA palette on every page family, in both modes:
Ritual (default) is white with light-blue tints and `#231F20` ink; Editorial sections are purple `#6A3277`
with a white primary pill; Clinical tokens are white/blue; dark mode is `#231F20`; every `--script` is
`#D7282F`. All colours were mapped by role (Part A), then corrected where the programmatic audits proved a
combination failed (B.5). No layout, spacing, typography, markup, copy, animation or JavaScript behaviour
changed (B.9, B.11).

### B.2 Files changed

| Group | Files |
|---|---|
| Stylesheet | `css/kotiva.css` |
| Root pages (inline `<style>` / `style=""` / JS colour literals, `?v=` tokens) | `index.html`, `shop.html`, `about.html`, `contact.html`, `journal.html`, `product.html`, `routine-finder.html`, `science.html`; `privacy-policy.html`, `terms.html` (tokens only) |
| Generators → regenerated output | `scripts/generate-product-pages.js` → `product/*.html` (25); `scripts/generate-ingredients-page.js` → `ingredients.html` |
| Assets | `assets/kotiva-logo-white.svg` (fill → `#FFFFFF`), `assets/apple-touch-icon.png` (cream plate → white) |
| Icon script | `scripts/generate-icons.py` (`PLATE` → white, so a future regeneration cannot reintroduce cream) |
| Test registration | `package.json` (`validate-palette.mjs` added to `npm test`; audit scripts registered) |
| New | `docs/COLOUR-MIGRATION.md`, `docs/colour-migration/*` (inventories, baseline, audit reports, evidence); `scripts/validate-palette.mjs`, `contrast-audit.mjs`, `computed-style-audit.mjs`, `screenshot-pages.mjs`, `compare-screenshots.mjs`, `colour-inventory.mjs`, `colour-diff-audit.mjs`, `scripts/lib/*` |
| Not changed | `js/layout.js`, `js/animations.js` (no colour literals; `updateNavLogo()` conditions verified correct), `favicon.svg`, `favicon-48.png` (no cream), all raster photography, `data/`, `js/data-*.js`, `js/routine-*.js`, `README.md`, `IMAGE-PROMPTS.md`, `llms.txt` |

Cache-bust tokens bumped in every reference (38 each): `kotiva.css?v=v4m → kc1`,
`kotiva-logo-white.svg?v=lg1 → lg2`, `apple-touch-icon.png?v=20260720 → 20260915`.

### B.3 Old-palette occurrences by family

Source: `inventory-before.json` (456 literals) vs `inventory-after.json` (489 literals).

| Family | Before | Migrated | Intentionally retained | Unresolved | After |
|---|---|---|---|---|---|
| Warm ink | 149 | 149 | 0 | 0 | 0 |
| Cream | 120 | 120 | 0 | 0 | 0 |
| Bronze | 90 | 90 | 0 | 0 | 0 |
| Free-from green (incl. `#2d6a42`) | 58 | 58 | 0 | 0 | 0 |
| Clinical blue / neutrals | 14 | 14 | 0 | 0 | 0 |
| Error red | 5 | 5 | 0 | 0 | 0 |
| Warm neutral text | 2 | 2 | 0 | 0 | 0 |
| Terracotta | 2 | 2 | 0 | 0 | 0 |
| Unlisted (comment-only) | 2 | 2 | 0 | 0 | 0 |
| Black `rgba(0,0,0,…)` | 3 | 1 (`.category-card-arrow` background — not a shadow) | 2 (editorial `--shadow` / `--shadow-hover`) | 0 | 2 |
| Approved already | 11 | — | 11 | 0 | — |

After: **487 approved-palette literals + 2 retained black shadows; 0 old-palette literals.**

### B.4 Token mapping implemented

| Token | Ritual / root (light) | Editorial | Clinical | Dark mode (root + ritual) |
|---|---|---|---|---|
| `--bg` | `#FFFFFF` | `#6A3277` | `#FFFFFF` | `#231F20` |
| `--bg-alt` | `rgba(138,183,233,0.18)` | `#6A3277` | `rgba(138,183,233,0.18)` | `rgba(255,255,255,0.04)` |
| `--bg-card` | `rgba(138,183,233,0.10)` | `rgba(255,255,255,0.07)` | `rgba(138,183,233,0.10)` | `rgba(255,255,255,0.07)` |
| `--fg` | `#231F20` | `#FFFFFF` | `#231F20` | `#FFFFFF` |
| `--fg-mid` / `--fg-dim` | `rgba(35,31,32,0.72)` / `0.62` | `rgba(255,255,255,0.78)` / `0.64` | `rgba(35,31,32,0.72)` / `0.62` | `rgba(255,255,255,0.78)` / `0.64` |
| `--accent` / `--accent-light` | `#8AB7E9` | `#8AB7E9` | `#005DBA` | `#8AB7E9` |
| `--accent-deep` (legacy) | `#005DBA` | `#FFFFFF` | `#005DBA` | `#FFFFFF` |
| `--accent-text` | `#005DBA` | **`#FFFFFF`** (B.5) | `#005DBA` | `#8AB7E9` (7.79:1 on `#231F20`) |
| `--border` / `--border-strong` | `rgba(35,31,32,0.12)` / `0.30` | `rgba(255,255,255,0.18)` / `0.40` | `rgba(0,93,186,0.15)` / `0.40` | `rgba(255,255,255,0.18)` / `0.40` |
| `--script` | `#D7282F` | `#D7282F` | `#D7282F` | `#D7282F` |
| `--shadow` / `--shadow-hover` | `rgba(35,31,32,.08/.14)` | `rgba(0,0,0,.30/.45)` | `rgba(35,31,32,.06/.12)` | (inherits theme) |
| Legacy aliases | `--off-white`/`--cream #FFFFFF`, `--black #231F20`, `--bronze #8AB7E9`, `--muted rgba(35,31,32,0.62)`, `--border-color rgba(35,31,32,0.12)` | | | |
| `--k-*` palette | all ten defined in `:root`; **0 `var()` references** — retained as official palette tokens, currently unused (brief §6.10b) | | | |

Dark mode does not override editorial or clinical sections (verified: editorial stays purple in dark mode;
clinical keeps its own tokens). Skip link: `#005DBA`/white in light, white/`#231F20` in editorial and dark mode.

### B.5 Role/token corrections made because a combination measured as failing

Every change below uses an approved colour or an approved/baseline alpha — no new colour was introduced.

| Measured failure | Before fix | Change (colour-only) |
|---|---|---|
| Editorial small accent text `#8AB7E9` on `#6A3277` (eyebrows on home/about/science, ritual foot link, shop hero eyebrow, bestsellers eyebrow) | 4.29:1 | Editorial `--accent-text` → `#FFFFFF` |
| Translucent `--bg-card` composited over grid separators (`background: var(--border)`) turned cards grey (`#DCE0E5` light / `#575455` dark) → `--fg-dim` 4.15:1, `.product-card-cta` 3.57:1 | 4.15 / 3.57 | `.product-card`, `.pdp-fact`, about `.value-card`, routine `.result-time-block`: `background: linear-gradient(var(--bg-card), var(--bg-card)), var(--bg)` — the same approved surface, composited over the ground instead of the separator |
| `--fg-dim` small text on `--bg-alt` (footer bottom line, "Coming soon", trust-bar sub-lines, journal newsletter eyebrow/note/placeholder, contact info label) | 4.43:1 | those rules → `var(--fg-mid)` |
| Contact success / error message text (green/red on the dark-mode surface) | 1.33 / 2.62 (dark), 3.79 (error, light) | text → `var(--fg)`; status tint `rgba(0,69,57/215,40,47, 0.06)` and border `0.3` kept |
| Photo text under the purple veil (lighter than the old near-black veil): mobile hero CTA 3.33, hero sub-line 4.26, shop hero sub-line 2.73, nav links over hero edges 4.23 / 3.60 | see left | Veil alphas raised **within the audited editorial-overlay baseline**: `index.html` `.hero-scrim-top` .16→.46; mobile `.hero-overlay` stops .62→.70 (34%, 72%) and .28/.25→.62; `shop.html` overlay stops .55→.70, .20→.46; shop hero sub-line → `#FFFFFF` |
| About CTA strip (fixed light accent in both modes) — button text inherited white in dark mode | 2.09:1 | `.about-cta-strip { color: var(--black) }` |
| Journal newsletter error outline `rgba(215,40,47,0.4)` | 1.87 light / 1.41 dark | alpha → `0.85` (existing status alpha): **3.74 light (pass)**; dark 2.44 → no red alpha reaches 3:1 → **BLOCKED — DESIGNER SIGN-OFF** |
| Primary-button hover `#231F20` on `#005DBA` (planned in A.10) | 2.6:1 | `.btn-gold:hover { color: var(--bg) }` → 6.41 light / 16.30 dark |

### B.6 Accessibility audit (programmatic WCAG)

`scripts/contrast-audit.mjs`, full detail in `docs/colour-migration/contrast-audit.md` (+ `.json`). Pre-migration
run on `28bc619` in `contrast-audit-baseline.md`.

| | Pre-migration (`28bc619`) | After migration |
|---|---|---|
| Text measurements | 5,540 | 5,540 |
| **FAIL (text)** | **501** | **0** |
| **FAIL (states / non-text)** | **40** | **0** |
| PASS (text / states) | 4,289 / 72 | 4,790 / 114 |
| BLOCKED — designer sign-off | — | 18 text (script) + 2 states (status alpha rule) |
| Decorative (ghost glyphs, symbol-only ✦ / →) / disabled controls | 86 / 4 | 86 / 4 |
| Informational non-text below 3:1 | 6 | 4 |

States exercised and passing in every applicable cell: skip link focused; nav transparent and scrolled, link and
current link, hover; primary button hover in nav, ritual and editorial contexts; secondary button over the hero
photo; newsletter input focus border; footer logo focus-visible; open mobile menu links; filter pill active label
and fill; inactive pill hover; contact success/error messages, submit hover, FAQ focus-visible, select value
text, field placeholder; journal subscribed button, filter active, placeholder; quiz option hover/selected;
product breadcrumb hover; glossary link hover.

**Photo-text note.** Text over photography was not judged from CSS: the glyph box was screenshotted with the
text hidden and the ratio taken against every sampled pixel (5th percentile). Passing examples: hero title 5.25,
hero tagline 6.26, hero sub-line 4.65, hero CTA 5.04, nav over home hero 5.45 / 8.60, shop hero title 4.81,
eyebrow 4.61, sub-line 5.40, shop nav 4.86, category names 3.57 (large), category counts 4.63, bestsellers
header 8.83, about quote 6.54 / 7.24. Blocked script over photography: hero script accent 1.11, journal feature
script 1.20–1.23.

**Informational (not gated).** Inactive filter pill border light 1.27:1 and contact field underline light
1.90:1 — decorative boundaries on controls identified by their text labels; both were weaker before migration
(bronze .20/.50 on cream) and were not changed to chase 3:1 (brief §6.8). Dark mode: 3.69 / 3.75.

**Not measured by the static sweep, and why.** 266 offscreen (content scrolled horizontally out of view — the
marquee, bestseller rail cards beyond the rail, mobile category carousel, mobile filter row — and the unfocused
skip link), 214 hidden (closed mobile menu at opacity 0; rail "Discover" labels shown only on hover/focus),
162 occluded (closed mobile-menu links; PDP science-expander lines scrolled outside its 420px scroll area). The
focused skip link and open mobile menu were measured as states; the remaining elements use token/surface pairs
measured elsewhere (e.g. `#005DBA` on the white plinth 6.41, `rgba(35,31,32,0.72)` on white).

### B.7 Palette validator and secondary diagnostics

* `node scripts/validate-palette.mjs` (in `npm test`): **PASS** — 489 literals, 72 distinct normalised values,
  4 `--script` declarations all `#D7282F`.
* Known old-palette grep (§7): **no matches**.
* HEX inventory: only the ten approved colours. Non-colour matches the regex also catches: HTML numeric entities
  `&#10022;` `&#8599;` `&#9877;`, the URL fragment `#B54-cosmetics` in `js/data-full.js`, and binary raster files.
* RGB inventory: bases `255,255,255` · `35,31,32` · `138,183,233` · `0,93,186` · `106,50,119` · `215,40,47` ·
  `0,69,57`, plus `0,0,0` ×2 (retained editorial shadows). No `hsl`/`hsla` values.

### B.8 Computed-style verification

`scripts/computed-style-audit.mjs` → `docs/colour-migration/computed-style-audit.md`: **0 violations.**
Every rendered element and `::before`/`::after` on 11 page families × 4 cells (colour, background, borders,
outline, text-decoration, SVG fill/stroke, and colours inside box-shadow, text-shadow, filter and
background-image); the theme × mode fixture (tokens equal the table in B.4 for ritual/editorial/clinical in light
and dark — no leakage), including `:hover` and the focused skip link; nav surface and logo variant (white logo
transparent over hero and in dark mode, black logo on the scrolled light bar — correct on home, shop, product,
about); static `var()` resolution of every colour token in `kotiva.css`.

Mode initialisation (persisted via `localStorage`): no cream or brown surface at any moment. Light mode first
paints `#FFFFFF`. Dark mode is already `#231F20` at the first frame on home and about; on the product page one
`#FFFFFF` frame precedes `#231F20` because `layout.js` (which applies the saved mode) loads at the end of
`<body>` — pre-existing behaviour, left unchanged (no new theme behaviour, brief §6.9b).

### B.9 Screenshot regression

`docs/colour-migration/SCREENSHOT-REGRESSION.md`. Baseline re-captured from a clean worktree of `28bc619` with
transition-safe timing (the worktree was removed afterwards). **44 / 44 capture pairs, 0 structural
differences** (every element's box ±0.5px, font, visibility, image path and own text identical; the only image
difference, the bumped `?v=` token on the white logo, is ignored by design). Pixel change 76–100% where
decodable; some very tall full-page PNGs exceed the browser decoder and report pixel % as n/a (geometry still
compared). 0 console errors / failed requests before and after. Downscaled side-by-side evidence:
`docs/colour-migration/evidence/`.

### B.10 Generated-page consistency

Both generators edited first, then re-run. `scripts/check-consistency.js`: generated-drift **OK** (25 product
pages + `ingredients.html` byte-identical to a fresh generator run), version tokens consistent (38 references
each). Masked structure/content equivalence of every generated file against `28bc619`: **0 differing lines**
(`docs/colour-migration/NON-COLOUR-DIFF.md`).

### B.11 Zero-non-colour-diff review

`scripts/colour-diff-audit.mjs` masks colour literals, `var(--token)` names, `?v=` tokens and comments. Paths
outside the §5 allowed set: **0**. Residual masked lines, all reviewed and colour-only: literal ↔ token swaps
(dark wells → `var(--bg)`, placeholder gradients → `var(--bg-alt)`, sticky/nav surfaces → `var(--bg)`, `#111` →
`var(--bg-alt)`); colour declarations added to existing rules (`.btn-gold:hover` colour, `.about-cta-strip`
colour, dark `select` chevron, card surface compositing); colour-only rules added (`--k-*` tokens, theme-explicit
skip link, editorial `.btn-gold` + hover, dark `.filter-pill.active`); colour-comment wording; `package.json`
script registration; the icon script's plate comment. `git diff --stat 28bc619`: 43 files, 572 insertions, 554
deletions.

### B.12 Unresolved / ambiguous occurrences

None left unresolved. Judgement calls made by role (listed for review in B.13): category-card placeholder
gradients under photos → neutral `var(--bg-alt)` (they are not category indicators); product image wells and
bestseller plinths → white; dark wells outside editorial → `#231F20`, inside editorial → `var(--bg)` purple;
`.hero-script-accent` (script font) recoloured to `--script` red; about `.about-quote-text` (script font, white on a
dark panel) kept white as light text.

### B.13 Designer / client sign-off items

1. Alpha variants of approved colours are used for surfaces, secondary text, borders and overlays (§2.2); fallback
   as stated in the brief.
2. Editorial sections are purple `#6A3277`; dark **mode** is `#231F20`.
3. `#005DBA` carries accent text on light surfaces; `#8AB7E9` carries accent text only in dark mode (7.79:1).
   In editorial (purple) sections accent text is **white**, because `#8AB7E9` measured 4.29:1.
4. Primary buttons on purple are white-fill/black-text; on light surfaces `#8AB7E9` fill with `#231F20` text, hover
   `#005DBA` with white text.
5. Toner / Whitening accessible text substitutes — not applied: no category-coloured indicator exists (A.7).
6. Cleanser `#004539` / `#8AB7E9` pairing — not applied for the same reason.
7. Treatment, Serums, Body, Lip Care stay neutral; Skin Repair mapping awaits client confirmation.
8. Skip link is theme-explicit (`#005DBA`/white on light; white/`#231F20` on dark).
9. `--accent-deep` retained as a legacy token resolving to white on dark surfaces.
10. `rgba(0,0,0,.30/.45)` retained in the editorial `--shadow` / `--shadow-hover`.
11. Shop filter pills keep the single active-state behaviour (no category colouring); KOT012 *Whitening Hand Cream*
    (Hand Care) and KOT019 *Whitening Wash Gel* (Cleanser) line membership awaits client confirmation.
12. **BLOCKED — DESIGNER SIGN-OFF (script on purple / photography / dark):** `#D7282F` script preserved per the brief
    invariant, failing contrast at: home hero `.hero-script-accent` over the photo (1.11, large); home ritual strip
    `.t-script` on purple (1.81, large); journal feature `.jf-script` over the image (1.20–1.23); science
    `.feature-ing-claim` on purple (1.81); product `.pdp-claim` in dark mode at 390px (3.29, 18px ⇒ normal text);
    about `.t-script` "The Ripple Effect" in dark mode at 390px (3.29, normal text size). All are functional text.
13. **BLOCKED — DESIGNER SIGN-OFF (status alpha rule):** journal newsletter error outline in dark mode — no alpha of
    `#D7282F` reaches 3:1 on the dark surface (best existing status alpha 0.85 = 2.44:1; passes 3.74:1 in light mode).
14. Documented existing-overlay/shadow exceptions validated against the audited baseline: ink scrims over photography
    outside editorial sections `rgba(35,31,32, .75 / .35 / .90)` (category card overlay, category arrow chip, about
    hero quote); white inset highlights `rgba(255,255,255, .50 / .55)` (bestseller plinth, unused ritual shelf).
15. Editorial photo veils made stronger than a straight alpha transfer (B.5), using alphas from the audited baseline.
16. Translucent card surfaces are composited over `--bg` (B.5) so grid separators do not tint the whole card.
17. Contact success/error messages use ink text with status-coloured tint and border (green/red text fails on the
    dark-mode surface).
18. Dark-mode borders: scrolled nav border `rgba(138,183,233,0.18)`; filter bar border `var(--border-strong)`
    (old alpha .32 not in §2.2); theme-toggle border on scrolled dark nav `rgba(138,183,233,0.60)` (DEC-159
    equivalent).
19. `apple-touch-icon.png`: the brand-icon masters are not in the repository and Python is not installed, so the
    plate was replaced in place (only plate and cream→white keyline-edge pixels changed; glyph pixels untouched) and
    `generate-icons.py` now uses a white plate for the next real regeneration.
20. Free-from pills (`.pdp-free-pill`, text `rgba(0,69,57,0.6)`) never render: `freeFrom` is empty on all 25
    products. If the client populates it, that text (≈3.6:1 on white, 8px) must be re-verified under the status
    alpha rule before launch.

### B.14 Pre-existing issues found and left untouched (brief §5 "no auto-fix outside scope")

* `.btn-primary` has **no CSS rule** anywhere: the routine-finder "Next" `<button>`s render user-agent text colours
  (`buttontext`, and the UA `:disabled` grey `rgba(16,16,16,0.3)`). Recorded in the computed-style audit's
  pre-existing section; styling the class would add UI, so it is not fixed.
* `.btn-outline-dark` (about.html) has no CSS rule (its text now follows the CTA strip's ink colour).
* Undefined tokens: `--bronze-dim`, `--taupe`, `--border-light`, `--ease-out`, `--gutter` (`--pin-rail-vh` is set by JS).
* Dead CSS (no matching markup): `.ing-filter-btn` rules in `science.html`, `.ritual-shelf` / `.ritual-item`,
  `.product-detail-img-wrap`, `.testimonial-*`, `.concern-pill`, `.step-dot`/`.step-card`.
* No `<meta name="theme-color">` on any page — not added (markup change). The site's mode toggle therefore has no
  theme-color to synchronise; no synchronisation system was introduced.
* Saved dark mode is applied after `<body>` parses (`layout.js` at end of body): one light first frame on some pages.
* The pre-migration site failed WCAG contrast in 501 text measurements and 40 states; those were resolved only
  through the colour/token mapping itself (no structural fixes).

### B.15 Git report

| Item | Value |
|---|---|
| Starting commit | `28bc619cd75548f7b70c48e869a51e75d89d36ae` on `change-identity` |
| Starting dirty files | none (clean worktree) |
| Migration branch | `feat/brand-colours` (not committed — left for review) |
| Files modified by the migration | 43 tracked files (B.2) |
| Newly generated / added files | `docs/COLOUR-MIGRATION.md`, `docs/colour-migration/` (inventories, alpha baseline, old-palette families, contrast / computed-style / screenshot / non-colour-diff reports, evidence PNGs), 7 `scripts/*.mjs` audit scripts, `scripts/lib/` (5 modules) |
| Local-only artefacts (git-ignored) | `scratch/colour-migration/{before,after}/` full screenshot matrices; `node_modules/` from `npm ci` |
| Unexpected files changed | **0** |
