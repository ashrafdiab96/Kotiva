# KOTIVA™ Website — Source Handover

Premium skincare website for KOTIVA™. Static, hand-built site: vanilla HTML/CSS/JS, no framework,
self-hosted fonts. **No build step is required to serve these files** — open `index.html` locally,
or point any static file host at this folder as-is.

This is the full site: **11 hand-authored pages** (Home, Shop, Product template, About, Science,
Routine Finder, Journal, Contact, Privacy Policy, Terms, Ingredient Glossary) plus **25 pre-rendered
product detail pages** in `product/<slug>.html` (built once at generation time so search engines and
AI crawlers can read full product content without running JavaScript).

## No secrets required

Nothing in this folder needs an API key, password, or database credential to run. It's static files.

## Structure

```
*.html                                the 11 hand-authored pages
product/                              25 generated product detail pages
css/ js/ img/ assets/ fonts/          design system, page logic, media, self-hosted webfonts
data/routine-model.json               single source of truth for product + routine data
scripts/
  generate-product-pages.js           regenerates product/*.html from data/
  generate-ingredients-page.js        regenerates ingredients.html from data/
  generate-routine-model.js           regenerates js/routine-model.js from data/routine-model.json
  generate-icons.py                   regenerates favicon/app-icon set from brand-icon masters
  check-consistency.js                validates cache-bust tokens, data/page drift, single site origin
  check-global-consistency.js         validates shared header/footer/nav render identically site-wide
  test-routine-engine.mjs             tests for the Routine Finder logic
robots.txt / sitemap.xml / llms.txt   crawler + AI-agent directives
package.json / package-lock.json      one devDependency: Playwright (used by the two *-safari.js
                                       scripts below, for a historical Safari/WebKit bug class)
debug-safari.js / verify-safari-fix.js   optional dev diagnostics, not required to run the site
IMAGE-PROMPTS.md                      the original image-generation creative brief, kept for context
```

## Regenerating pages after a data change

```bash
npm install
KOTIVA_SITE_ORIGIN=https://kotiva.co node scripts/generate-product-pages.js
KOTIVA_SITE_ORIGIN=https://kotiva.co node scripts/generate-ingredients-page.js
node scripts/generate-routine-model.js
node scripts/check-consistency.js        # run before shipping — fails on drift
```

`KOTIVA_SITE_ORIGIN` controls the canonical/Open Graph URLs the generators write. It defaults to a
placeholder if unset — **always pass your real production origin explicitly.**

## Cache-bust discipline

Core CSS/JS assets are referenced with `?v=` version tokens and served with long-cache headers on
whatever host you deploy to. **Any change to a versioned asset requires bumping every `?v=`
reference for that asset in one pass** (all pages + both generator scripts), or visitors will keep
seeing the stale cached file indefinitely. `check-consistency.js` fails if tokens ever diverge —
run it before every deploy.

## Contact & newsletter forms — action needed before you go live independently

Both the contact form and the newsletter signup currently submit to a webhook operated by our
former development partner (`js/layout.js`). It will keep working as a courtesy, but it is not
part of this codebase and is not guaranteed to run indefinitely. Before (or shortly after) taking
this site live on your own infrastructure, point the form submission target in `js/layout.js` at
your own form backend, email-forwarding service, or CRM integration.

## Fonts

`fonts/` ships **Futura Now Var** and **Manus** as self-hosted `.woff2` webfont files. Confirm your
license covers continued use on your own infrastructure before redeploying — we can put you in
touch with whoever originally licensed them if needed.

## No CI/deploy pipeline included

Deployment automation was specific to our former hosting arrangement and isn't part of this
handover. These are plain static files — deploy them with whatever static hosting you choose
(Netlify, Vercel, S3+CloudFront, your own server, etc.). Nothing here assumes a particular host.
