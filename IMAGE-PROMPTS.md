# KOTIVA™ — Image Generation Prompts (historical)

> **Superseded 2026-08-18.** The canonical, currently-authoritative prompt package is the DEM
> vault's `40_projects/kotiva/KOTIVA-IMAGE-GENERATION-MASTER-2026-08-18.md` (the same-named file
> in this repo is a pointer stub to it) —
> it audits and reconciles this file, `40_projects/kotiva/image-prompts-cycle3-2026-07-20.md`, and
> the client-package briefs into one document, with every reference path re-verified against the
> live repo/vault tree. Use that file for any new generation. This file is kept only as a record
> of what was tried before — its own Doctor Approved entries (below) are dead: v1 was never
> product-visible by design, v2 was generated, safety-checked, and set aside for a blank label
> (kept at `assets/_candidates/home-doctor-approved-generated-v2.webp`).
>
> Original preamble, for context: fills the remaining visual gaps in the site, excluding assets
> already in hand (category cards, journal images, the homepage hero). Each prompt below was
> self-contained; house style + size were baked into every prompt so the set would render as one
> cohesive brand system. After generating, the plan was to drop each file into the target path
> shown and wire the `src=` paths in.

**Brand palette referenced throughout:** deep espresso `#0C0804` · warm bronze/amber `#C4956A` · soft cream `#EDE7DC` · golden light.
**Export format:** `.webp`, quality ~82, sRGB.

---

## Homepage — `index.html`

### `KOT-HOME-01` — Doctor Approved portrait
- **Target file:** `assets/home-doctor-approved.webp`
- **Aspect / size:** 4:5 portrait — **1200 × 1500 px**

```
Luxury editorial beauty photography, 4:5 vertical portrait, 1200x1500 pixels, for a doctor-approved MENA skincare brand. A warm, approachable female dermatologist in a clean white coat gently examining a female patient's cheek in a softly-lit modern clinic. Late-30s Middle Eastern / North African doctor with a focused, reassuring expression; patient seated, calm, with glowing healthy skin. Warm amber light spilling from the right, blurred warm clinic background with out-of-focus shelving. Subject positioned slightly right-of-center, head-and-shoulders crop, generous soft-dark negative space on the left for later text overlay. Palette: deep espresso #0C0804 shadows, warm bronze #C4956A light, soft cream #EDE7DC midtones. Natural golden directional light, soft warm shadows, shallow depth of field, fine film grain. Clinical but warm, trustworthy, premium medical-beauty mood. Photorealistic, high-end, no text, no logos, no graphic overlays.
```

### `KOT-HOME-02` — Routine Finder lifestyle
- **Target file:** `assets/home-routine-ritual.webp`
- **Aspect / size:** 4:5 portrait — **1200 × 1500 px**

```
Luxury editorial beauty photography, 4:5 vertical portrait, 1200x1500 pixels, for a doctor-approved MENA skincare brand. A Middle Eastern / North African woman, 28–35, mid morning skincare ritual in a warm cream-toned bathroom, pressing a cream into her cheek with her fingertips, eyes softly closed, serene and confident. Golden morning light through a window, subtle botanical and linen styling, unbranded cream cosmetic vessels softly out of focus on the counter. Subject placed on the right third, dark warm gradient falloff on the left for headline text. Palette: deep espresso #0C0804, warm bronze/amber #C4956A, soft cream #EDE7DC. Natural golden light, soft warm shadows, shallow depth of field, fine film grain. Calm, aspirational, lifestyle editorial. Photorealistic, high-end, no text, no logos, no graphic overlays.
```

### `KOT-HOME-03` — Bestsellers ambient background *(optional)*
- **Target file:** `assets/home-bestsellers-bg.webp`
- **Aspect / size:** 16:9 wide — **1920 × 1080 px** *(sits at ~28% opacity behind product cards)*

```
Atmospheric wide background image, 16:9 landscape, 1920x1080 pixels, for a luxury MENA skincare brand. Extreme close-up of luminous "glass-skin" cheekbone and collarbone catching golden light, heavily blurred and abstract. Predominantly deep espresso #0C0804 shadow on the left transitioning to a warm amber #C4956A glow on the right. Very low detail, no facial features in focus — pure warm-light atmosphere designed to sit at low opacity behind white product cards. Soft film grain, cinematic warmth. Photorealistic, no text, no logos, no graphic overlays.
```

### Instagram strip — 6 square tiles
Replaces the 6 tiles that currently reuse the category images. All **1:1 square — 1200 × 1200 px**.

#### `KOT-IG-01`
- **Target file:** `assets/ig/ig-01.webp`
```
Luxury editorial beauty photo, 1:1 square, 1200x1200 pixels, MENA skincare brand. Macro close-up of a single drop of clear serum being pressed into a Middle Eastern / North African woman's cheek with a fingertip, dewy glow on the skin. Warm golden light, palette deep espresso #0C0804 / bronze #C4956A / cream #EDE7DC, shallow depth of field, fine film grain. Photorealistic, no text, no logos.
```

#### `KOT-IG-02`
- **Target file:** `assets/ig/ig-02.webp`
```
Luxury editorial product photo, 1:1 square, 1200x1200 pixels, MENA skincare brand. Overhead flatlay of unbranded cream-and-bronze skincare vessels on warm cream marble with a sprig of dried botanical and soft linen. Warm studio light, palette deep espresso #0C0804 / bronze #C4956A / cream #EDE7DC, gentle shadows, fine film grain, luxury styling. Photorealistic, no text, no logos, no readable labels.
```

#### `KOT-IG-03`
- **Target file:** `assets/ig/ig-03.webp`
```
Luxury editorial macro photo, 1:1 square, 1200x1200 pixels, MENA skincare brand. Extreme macro of healthy hydrated skin texture — visible pores, subtle peach-fuzz, a sheen of moisture catching golden light. Abstract, no full face. Palette deep espresso #0C0804 / bronze #C4956A / cream #EDE7DC, warm directional light, shallow depth of field, fine film grain. Photorealistic, no text, no logos.
```

#### `KOT-IG-04`
- **Target file:** `assets/ig/ig-04.webp`
```
Luxury editorial beauty photo, 1:1 square, 1200x1200 pixels, MENA skincare brand. A Middle Eastern / North African woman, early 30s, laughing naturally with clear bare glowing skin and minimal makeup. Warm golden window light, soft cream background. Genuine candid joy. Palette deep espresso #0C0804 / bronze #C4956A / cream #EDE7DC, soft warm shadows, shallow depth of field, fine film grain. Photorealistic, no text, no logos.
```

#### `KOT-IG-05`
- **Target file:** `assets/ig/ig-05.webp`
```
Luxury editorial product still life, 1:1 square, 1200x1200 pixels, MENA skincare brand. A single matte-green skincare tube standing on a sand-toned plaster surface with a long dramatic warm shadow and one bronze highlight edge. Unbranded, no readable label. Palette deep espresso #0C0804 / bronze #C4956A / warm sand, raking golden light, fine film grain, editorial. Photorealistic, no text, no logos.
```

#### `KOT-IG-06`
- **Target file:** `assets/ig/ig-06.webp`
```
Luxury editorial flatlay, 1:1 square, 1200x1200 pixels, MENA skincare brand. Morning-ritual top-down scene — several unbranded cream and amber cosmetic vessels arranged on rumpled natural linen beside a ceramic cup. Soft diffused light, calm cream palette, deep espresso #0C0804 / bronze #C4956A / cream #EDE7DC. Gentle shadows, fine film grain, serene. Photorealistic, no text, no logos, no readable labels.
```

---

## About page — `about.html`

### `KOT-ABOUT-01` — Brand hero portrait
- **Target file:** `assets/about-hero-portrait.webp`
- **Aspect / size:** 4:5 portrait — **1200 × 1500 px** *(a code grain-overlay sits on top — keep the image clean)*

```
Fine-art editorial beauty portrait, 4:5 vertical, 1200x1500 pixels, for a luxury doctor-approved MENA skincare brand. A poised Middle Eastern / North African woman in her 30s in soft profile, eyes closed, face tilted up into warm golden light — embodying the idea "skin that performs, a life in motion." Flawless luminous skin, bronze tones. Strong directional light from the upper right, dramatic soft shadow on the left, deep espresso #0C0804 background. Head-and-shoulders crop, contemplative and elegant. Palette deep espresso #0C0804 / warm bronze #C4956A / soft cream #EDE7DC, shallow depth of field, fine film grain. Photorealistic, high-end, no text, no logos, no graphic overlays.
```

---

## Shop page — `shop.html`

### `KOT-SHOP-01` — Collection hero banner
- **Target file:** `assets/shop-hero-flatlay.webp`
- **Aspect / size:** 16:9 wide — **1920 × 1080 px** *(crops to a ~360px-tall band; keep composition wide-crop safe)*

```
Wide cinematic editorial product banner, 16:9 landscape, 1920x1080 pixels, for a luxury MENA skincare brand. Overhead flatlay of an unbranded cream-and-bronze skincare collection — bottles, tubes, droppers, a jar — artfully scattered across a warm dark espresso linen surface, raking golden side-light casting long elegant shadows. Products clustered toward the right two-thirds; deep shadowed negative space on the lower-left for a headline. Palette deep espresso #0C0804 / warm bronze #C4956A / soft cream #EDE7DC, rich moody premium e-commerce mood, fine film grain. Composition must survive a wide letterbox crop (key elements kept within the vertical center band). Photorealistic, no text, no logos, no readable labels.
```

---

## Out of scope — handle separately

### 🔴 12 missing product shots — need real photography, NOT AI
These are currently grey SVG placeholders and represent **real physical products** with specific packaging and regulated medical label copy. AI cannot reproduce the actual bottle/label, and brand rules forbid inventing label text. **Client must supply real product photos** for:

`KOT004` · `KOT006` · `KOT007` · `KOT010` · `KOT012` · `KOT013` · `KOT014` · `KOT015` · `KOT017` · `KOT019` · `KOT021` · `KOT024`

*(Optional: I can generate neutral generic "coming soon" vessel renders as temporary stand-ins — ask if wanted.)*

### ⚪ Text-only heroes — intentionally typographic, not gaps
Science, Routine-Finder, and Contact heroes are dark + border + type by design. Can be given a faint background image later if richness is wanted — flag it and I'll spec them.

---

## Quick reference

| Label | Slot | Target file | Size |
|-------|------|-------------|------|
| KOT-HOME-01 | Doctor Approved | `assets/home-doctor-approved.webp` | 1200×1500 (4:5) |
| KOT-HOME-02 | Routine Finder CTA | `assets/home-routine-ritual.webp` | 1200×1500 (4:5) |
| KOT-HOME-03 | Bestsellers bg *(opt)* | `assets/home-bestsellers-bg.webp` | 1920×1080 (16:9) |
| KOT-IG-01…06 | Instagram strip ×6 | `assets/ig/ig-01…06.webp` | 1200×1200 (1:1) |
| KOT-ABOUT-01 | About hero | `assets/about-hero-portrait.webp` | 1200×1500 (4:5) |
| KOT-SHOP-01 | Collection hero | `assets/shop-hero-flatlay.webp` | 1920×1080 (16:9) |

---

## Cycle 4 — expanded visual system (2026-08-18)

> **Consolidation, not a rewrite.** Most of what this round asked for already existed, written and
> unused, in `40_projects/kotiva/image-prompts-cycle3-2026-07-20.md` — a more rigorous prompt system
> than this file's own (locked 4-woman cast KV-A…KV-D, a standalone HARD RULE block, a negative
> prompt appended to every generation, an accept/reject checklist). That system supersedes the
> lighter style line at the top of this file for every NEW prompt from here on. Existing KOT-HOME-02/
> KOT-IG-* prompts above are left as historical record, not rewritten.
>
> **Two standing constraints recorded in that doc, still binding:** do not regenerate the homepage
> hero photo itself — client rejected an inclusive-cast version 2026-07-19 and settled on the
> original subject, restored and approved. (A same-subject tonal regrade, not a photo swap, was
> applied this session — flagged to the operator, not self-adjudicated.) Do not touch
> `assets/about-hero-portrait.webp` — client-approved as-is (DEC-335).

### Style system for every prompt below

```
Photorealistic editorial DSLR beauty photography for a doctor-approved skincare brand.
Warm golden-hour lighting: soft, directional, luminous, honeyed. Shallow depth of field,
sharp focus on skin texture, fine film grain.
Palette: deep espresso #0C0804 shadows, warm bronze #C4956A light, soft cream #EDE7DC
midtones. Backgrounds dark, or warm and softly blurred.
Skin is dewy, healthy, GLOWING and REAL — visible natural texture, pores, fine detail,
subtle freckles. NOT airbrushed, NOT plastic, NOT waxy.
Styling minimal and natural: cream/ivory linen shirt, robe or knit; natural undone hair;
barely-there no-makeup makeup; modest styling; no loud or branded jewellery.
Expression serene, calm, quietly confident — aspirational but real, never a stiff
stock-photo smile.
```

**HARD RULE — no invented text or branding.** Never render any readable text, logo, brand name,
product label, certification badge or packaging copy anywhere in the frame — not on bottles,
droppers, coats, walls or background objects. Any product or dropper that appears must be plain and
unbranded. If you cannot render a product without inventing a label, crop it out or blur it heavily.
This is the single highest-frequency failure and the exact one this project shipped to the client's
Review surface this week (four of six ritual-strip images).

**Negative prompt — append to every generation:**
```
no text, no watermark, no logo, no readable labels, no cold or blue or clinical-white tones,
no harsh flash, no plastic or AI-looking skin, no extra fingers, no distorted hands,
no busy backgrounds, no heavy makeup, no studio seamless grey, no illustration, no 3D render
```

**Acceptance test before any generated file is used — not a formality:** open it at 100%, scan the
*entire* frame including blurred backgrounds and object edges for text, count fingers on every visible
hand, and hold it beside the category-grid anchor for light/tone match. A prompt that *says* "no text"
is not evidence a specific output has none.

---

### `KOT-HOME-01-v3` — Doctor Approved, reference-anchored, FOR OPERATOR GENERATION

- **Target file:** `assets/home-doctor-approved-v3.webp`
- **Aspect / size:** 4:5 portrait — 1200×1500 px
- **Status:** ready to generate. **Operator-generated, not agent-generated** (2026-08-18 ruling) —
  the agent prepares the product choice + prompt + reference instructions below; the operator runs
  the generation and returns the file for the 100%-inspection gate before it is wired in.
- **Supersedes `KOT-HOME-01-v2`** (blank-label version, generated and safety-checked 2026-08-18,
  saved at `assets/_candidates/home-doctor-approved-generated-v2.webp` — kept as a reference for
  what "safe but not product-identifiable" looks like, not a candidate for use).

#### A. The exact product — chosen, not left open

**Kotiva Micellar Water — `KOT001` — slug `micellar-water`.**

| Field | Value |
|---|---|
| Product name | Kotiva Micellar Water |
| SKU | KOT001 |
| Master file (generate/reference from THIS one — 4000×4000, not the 1024px alternate) | `85_assets/kotiva/masters/product-deliverables-2026-06/Products/Kotiva Micellar Water.png` |
| Shipped web asset (lower-res, for comparison only) | `60_repos/kotiva-website/assets/products/kot001-micellar-water.webp` (1024×1024) |
| Live URL, if the generator needs one rather than an upload | `https://kotiva-working.ashater.com/assets/products/kot001-micellar-water.webp` |
| Site data | `category: "Cleanser"`, `skinType: "All Skin Types"`, `bestSeller: true`, `id: 1` (`js/data-lite.js`) |

**Why this SKU and not another:**
1. **`skinType: "All Skin Types"`** — the Doctor Approved section is the FIRST trust-building moment
   on the page, before any concern-based segmentation. A whitening cream or acne treatment silently
   tells part of the audience "this section isn't for you." Micellar water excludes no one.
2. **It is SKU `01` in the site's own bestseller rail** — the site already treats it as the flagship.
   Using it here connects the trust section to the product section below rather than introducing a
   seventh, otherwise-unfeatured product.
3. **The section's own existing credential badges are cleanser-specific.** `Sulphate-Free` is a claim
   that is only meaningful for a rinse-off/cleansing product — it is close to meaningless attached to
   a serum or cream. The section's badge set already implies a cleanser was the intended subject;
   Micellar Water is the correct match for copy that already exists, not a new direction.
4. It was **not** the product in the rejected `dr.jpg` photo (Whitening Night Cream, `KOT017`) or the
   blank-label v2 generation — a fresh choice, reasoned independently, not inherited from either
   discarded attempt.

#### B. The production prompt

```
Photorealistic editorial DSLR beauty photography, 4:5 vertical portrait, 1200x1500 pixels, for a
doctor-approved skincare brand.

SUBJECT REFERENCE: the product held in this image must match the attached reference photo of
Kotiva Micellar Water exactly — same bottle shape (tall cylindrical pump bottle), same cap and
pump head, same label layout, same label colours and printed text, same proportions. Do not
redesign, restyle, simplify, or invent any part of the packaging. If the label's printed text
cannot be rendered legibly and accurately, render the label AS A SOFT-FOCUS / GENTLY BLURRED
SURFACE rather than inventing alternative or approximate text — a blurred true label is
acceptable, a sharp invented one is not.

COMPOSITION / FRAMING: a warm, approachable female dermatologist in a clean white coat, late-30s
Middle Eastern/North African, focused and reassuring, holding the Micellar Water bottle upright
at chest height, angled slightly toward the camera so the label is visible but not perfectly
flat-on (a natural presenting gesture, not a product-shot pose). Head-and-shoulders-and-hands
crop, subject positioned slightly right-of-centre. Generous, calm negative space on the left
third of the frame for a headline to sit over later — this space should be the CALMEST, most
evenly-lit part of the frame, not the darkest.

ENVIRONMENT: a softly-lit modern clinic interior, warm wood or stone tones, soft out-of-focus
shelving in the background suggesting a considered space without being a specific readable
setting.

LIGHTING / COLOUR TREATMENT: this image sits directly above another approved image on the same
page (a woman applying cream in warm morning light, bright and luminous — L* ~52 on a 0-100
scale, i.e. a genuinely LIGHT image, not a dim one). Match that register: warm, bright, daylight-
leaning golden light, NOT a dim evening/spa mood. Avoid heavy shadow pooling. The two images
should read as photographed in the same session, same light quality, same warmth — not as two
different moods stitched together.

MODEL / CLINICIAN TREATMENT: warm, competent, quietly confident — a real professional, not a
stock-photo smile. No visible name badge, no visible certifications, no readable text of any
kind on the coat or in the background.

SKIN / HAND REALISM: dewy, healthy, glowing, REAL skin — visible pores and natural texture, not
airbrushed, not plastic, not waxy. CRITICAL: correct, natural hand and finger anatomy holding
the bottle — five fingers per hand, realistic proportions, no distortion, no fused or extra
fingers. Check the hands specifically before accepting the result.

HOUSE STYLE (do not vary): warm golden-hour lighting, soft and directional. Shallow depth of
field, sharp focus on the product label and the model's eyes, fine film grain. Palette: deep
espresso #0C0804 shadows, warm bronze #C4956A accents, soft cream #EDE7DC midtones — but overall
LIGHTER and BRIGHTER than a typical espresso-heavy Kotiva image, per the lighting note above.

REALISTIC SCALE / DEPTH: the bottle should read at its true relative scale against the hand
holding it — do not oversize or miniaturise it. Shallow depth of field should keep the label
sharp while the background falls softly out of focus.

Photorealistic, high-end editorial, no text, no watermark, no logo anywhere except the product's
own real label, no readable text or lettering anywhere in the frame OTHER than the product label
itself, no certification badges beyond what is already printed on the real label, no printed
markings on the coat/walls/shelving, no cold or blue or clinical-white tones, no harsh flash, no
plastic or AI-looking skin, no extra fingers, no distorted hands, no busy backgrounds, no heavy
makeup, no studio seamless grey, no illustration, no 3D render.
```

#### C. Reference-image instructions — exact, not left to judgement

1. **Upload this exact file as the reference image:**
   `85_assets/kotiva/masters/product-deliverables-2026-06/Products/Kotiva Micellar Water.png`
   (4000×4000, transparent background). Do **not** substitute the 1024px alternate
   (`85_assets/kotiva/masters/products/006-MICELLAR-WATER.png`) or any other SKU's render.
2. **Set the reference role to "subject"** if your tool distinguishes reference roles (style vs.
   subject vs. character) — you want the PRODUCT locked, not merely a style influence.
3. **What to expect from label fidelity — read before generating, not after:** reference-image
   anchoring is reliably good at reproducing bottle *shape, cap, proportions and overall colour-
   blocking*. It is **not** reliable at reproducing small printed *text* pixel-accurately — this is
   a known limitation of current image generation, not specific to this tool. Expect one of three
   outcomes: (a) the label renders blank/softly blurred — safe, usable as-is; (b) the label renders
   with genuinely accurate small text — rare, check it letter-by-letter before trusting it; (c) the
   label renders with plausible-looking but WRONG or garbled text — this is the dangerous outcome
   and the one to watch for specifically, because at a glance it can look fine.
4. **The acceptance test is the same one this file already states for every image:** open the
   result at 100%, and check the label text against the real master character-by-character. If it
   does not match exactly, do not use the file as generated — either regenerate, or send it to the
   agent to have the label region cloned in from the real master over the generated one (a real
   compositing step, not a re-prompt).
5. **Send the raw generated file back before it is wired into the site** — the 100%-inspection gate
   this project's own incident history exists to enforce is not optional for this image because it
   was operator-generated rather than agent-generated. Same rule, same reason.

---

## About page — additions (existing hero untouched, DEC-335)

### `KOT-ABOUT-02` — Second editorial image, "Our Journey" section

The page currently carries exactly one image (the approved hero). The `#journey` section (four
numbered milestones: Starting Point → Gateway to the GCC → Regional Growth → Long-Term Vision) is
pure text — the clearest opportunity for a second image without touching the approved hero or the
page's information architecture.

- **Target file:** `assets/about-journey.webp`
- **Aspect / size:** 3:2 landscape — 1800×1200 px (sits as a wide band above or beside the milestone list)
- **Cast:** KV-A (consistency with the launch-market anchor used elsewhere)

```
Photorealistic editorial DSLR lifestyle photography, 3:2 landscape, for a doctor-approved
skincare brand. A quiet, considered workspace moment: a Gulf/Arabian woman, 30s, reviewing
formulation notes at a warm wooden desk, soft natural light from a window at her side, a single
unbranded skincare bottle and a folded cream linen cloth nearby, out of focus. Contemplative,
unhurried — building something, not posing for a brand shoot.
[+ house style block + hard rule + negative prompt]
ABSOLUTELY no readable text on any notes, papers, screens or packaging in frame — blur or angle
away anything with visible writing.
```

---

## Science page — imagery (layout decision still open, prompts already exist)

`science.html` has **no image slots** by design — the vault records this as intentional, not a gap.
The four formula-macro prompts already written for exactly this purpose (`image-prompts-cycle3-2026-07-20.md`
Group C, `C-01`…`C-04` — texture swatch, serum ribbon, formula-on-skin, droplet-on-glass, all 3:2
1800×1200, all zero-people macro shots) were never placed because nobody decided *where*. Two
concrete placement options, not a rebuild:

1. **`.philosophy-section`** — currently a plain text block (`Formulation Philosophy`, science.html
   ~line 349). A single formula-macro as a full-bleed band background at low opacity, same treatment
   as the homepage bestsellers background, would give the section depth without adding a new grid.
2. **`.actives-section`** — currently a text-only ingredient grid (~line 391). C-01/C-02 as a small
   2-image strip between the intro copy and the ingredient list would break up the page's current
   "all type, no image" character most directly — this is the stronger candidate for a first attempt.

Both are additive, reversible, and do not touch the page's typographic-hero identity. Recommend
placing C-02 (serum ribbon) in the actives-section strip first — it is the most on-brand single image
for a "science" page (formula in motion, not a face) and needs no new copy to make sense.

---

## Shop page — hero replacement (material mismatch confirmed, not assumed)

**The current `shop-hero-flatlay.webp` does not match real KOTIVA packaging — checked against the
actual product masters, not inferred.** It shows a boutique glass-dropper-and-brushed-bronze flatlay:
frosted glass bottles, copper pump heads, a bronze dish, silk fabric. KOTIVA's real range
(`85_assets/kotiva/masters/products/*.png`, `60_repos/kotiva-website/assets/products/*.webp`) is
**opaque colour-blocked plastic** — pump bottles and squeeze tubes in blue-grey, lilac, orange,
forest green, with printed white label panels — pharmacy-adjacent, not boutique-glass. The existing
hero is generic luxury-skincare stock imagery in a different material language than the brand it
sits above.

### `KOT-SHOP-01-v2` — Collection hero, correct material language

- **Target file:** `assets/shop-hero-flatlay-v2.webp`
- **Aspect / size:** 16:9 wide — 1920×1080 px (crops to a ~360px band; keep composition wide-crop safe)

```
Photorealistic editorial DSLR still-life photography, 16:9 landscape, for a doctor-approved
skincare brand. Overhead flatlay of an unbranded skincare collection — opaque colour-blocked
plastic pump bottles and soft-touch squeeze tubes in blue-grey, dusty lilac, warm orange and
deep green, plain white label panels left completely blank — artfully scattered across a warm
dark espresso linen surface. Raking golden side-light casting long elegant shadows. Products
clustered toward the right two-thirds; deep shadowed negative space on the lower-left for a
headline.
[+ house style block + hard rule + negative prompt]
Composition must survive a wide letterbox crop — keep key elements within the vertical centre
band. Materials are matte and opaque plastic, NOT glass, NOT brushed metal, NOT copper — this is
a clinical-pharmacy colour-blocked range, not a boutique apothecary set.
```

---

## Homepage — lower-page editorial imagery (the "six images" ask)

The six images this ask refers to are the **community/Instagram strip** — `image-prompts-cycle3-2026-07-20.md`
Group B, `KOT-COM-01`…`KOT-COM-06`, already fully written (mirror moment, glow portrait, flatlay with
hands, window light, evening ritual, two-together — full cast spread KV-A through KV-D). That doc
also names the live defect they fix: the homepage currently reuses the six category images a second
time in this strip, so the page visibly repeats itself on scroll. Nothing new to draft here — the
work is generating and reviewing those six against the checklist in that file, §5.

---

## Quick reference — cycle 4 additions

| Label | Slot | Target file | Size | Status |
|-------|------|-------------|------|--------|
| KOT-HOME-01-v2 | Doctor Approved | `assets/home-doctor-approved-v2.webp` | 1200×1500 (4:5) | attempted, pending review |
| KOT-ABOUT-02 | About — Our Journey | `assets/about-journey.webp` | 1800×1200 (3:2) | drafted, not generated |
| Science C-01…C-04 | Formula macros | `assets/formula/formula-0N.webp` | 1800×1200 (3:2) | already written (cycle3), not generated |
| KOT-SHOP-01-v2 | Shop collection hero | `assets/shop-hero-flatlay-v2.webp` | 1920×1080 (16:9) | drafted, not generated |
| KOT-COM-01…06 | Homepage community strip | `assets/community/ig-0N.webp` | 1200×1200 (1:1) | already written (cycle3), not generated |
