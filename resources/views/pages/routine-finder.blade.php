@extends('layouts.app')

@section('meta')
  <title>Routine Finder — kotiva™</title>
  <meta name="description" content="Find your perfect kotiva™ skincare routine. Answer a few questions about your skin type and concerns.">
  <link rel="canonical" href="{{ config('kotiva.site_origin') }}/routine-finder">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="KOTIVA">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Routine Finder — kotiva™">
  <meta property="og:description" content="Find your perfect kotiva™ skincare routine. Answer a few questions about your skin type and concerns.">
  <meta property="og:url" content="{{ config('kotiva.site_origin') }}/routine-finder">
  <meta property="og:image" content="{{ config('kotiva.site_origin') }}/assets/hero-warm-skincare.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Routine Finder — kotiva™">
  <meta name="twitter:description" content="Find your perfect kotiva™ skincare routine. Answer a few questions about your skin type and concerns.">
  <meta name="twitter:image" content="{{ config('kotiva.site_origin') }}/assets/hero-warm-skincare.webp">
@endsection

@push('styles')
@verbatim
<style>
.rf-hero {
  padding-top: calc(var(--nav-h) + 72px);
  padding-bottom: 72px;
  border-bottom: 1px solid var(--border);
}
/* QUIZ UI */
.quiz-section { padding: var(--gap-section) 0; }
.quiz-wrap { max-width: 860px; margin: 0 auto; }
.quiz-step { display: none; }
.quiz-step.active { display: block; }

.quiz-progress {
  display: flex;
  gap: 6px;
  margin-bottom: 48px;
}
.quiz-progress-dot {
  height: 2px;
  flex: 1;
  background: var(--border);
  transition: background 0.4s;
}
.quiz-progress-dot.done { background: var(--bronze); }
.quiz-progress-dot.active { background: var(--off-white); }

.quiz-step-label {
  font-family: var(--font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--accent-deep);
  margin-bottom: 16px;
}
.quiz-question {
  font-family: var(--font-display);
  font-size: clamp(24px, 3vw, 44px);
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--off-white);
  line-height: 1.1;
  margin-bottom: 12px;
}
.quiz-sub {
  font-size: 14px;
  color: rgba(255,255,255,0.64);
  margin-bottom: 44px;
  font-family: var(--font-body);
}
.quiz-options {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 40px;
}
.quiz-option {
  padding: 24px 28px;
  border: 1px solid var(--border);
  cursor: pointer;
  transition: all 0.25s;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: transparent;
  text-align: left;
}
.quiz-option:hover {
  border-color: var(--bronze);
  background: rgba(138,183,233,0.25);
}
.quiz-option.selected {
  border-color: var(--bronze);
  background: rgba(138,183,233,0.25);
}
.quiz-option-title {
  font-family: var(--font-display);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--off-white);
}
.quiz-option-desc {
  font-size: 12px;
  color: rgba(255,255,255,0.64);
  line-height: 1.5;
  font-family: var(--font-body);
}
.quiz-option.selected .quiz-option-title { color: var(--accent-text); }
.quiz-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-top: 32px;
  border-top: 1px solid var(--border);
}
.quiz-back {
  font-family: var(--font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.64);
  cursor: pointer;
  background: none;
  border: none;
  transition: color 0.2s;
}
.quiz-back:hover { color: var(--off-white); }
/* RESULT */
.result-section { display: none; padding: var(--gap-section) 0; }
.result-section.visible { display: block; }
.result-header {
  text-align: center;
  max-width: 600px;
  margin: 0 auto 64px;
}
.result-routine-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px;
  background: var(--border);
  margin-bottom: 60px;
}
.result-time-block { background: var(--black); }
.result-time-label {
  padding: 24px 32px;
  border-bottom: 1px solid var(--border);
  font-family: var(--font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--accent-deep);
  display: flex;
  align-items: center;
  gap: 12px;
}
.result-time-icon {
  width: 24px; height: 24px;
  border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
}
.result-steps { padding: 8px 0; }
.result-step {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  padding: 20px 32px;
  border-bottom: 1px solid var(--border-light);
  transition: background 0.2s;
  cursor: pointer;
}
.result-step:last-child { border-bottom: none; }
.result-step:hover { background: var(--bg-alt); }
.result-step-num {
  font-family: var(--font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.2em;
  color: var(--accent-deep);
  flex-shrink: 0;
  min-width: 28px;
  margin-top: 2px;
}
.result-step-label {
  font-family: var(--font-display);
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.64);
  margin-bottom: 4px;
}
.result-step-product {
  font-family: var(--font-display);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--off-white);
  line-height: 1.2;
}
.result-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}
.result-retake {
  font-family: var(--font-display);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.64);
  border-bottom: 1px solid rgba(255,255,255,0.18);
  padding-bottom: 3px;
  cursor: pointer;
  background: none;
  border: none;
  text-decoration: underline;
  text-underline-offset: 4px;
  transition: color 0.2s;
}
.result-retake:hover { color: var(--off-white); }
@media (max-width: 680px) {
  .quiz-options { grid-template-columns: 1fr; }
  .result-routine-grid { grid-template-columns: 1fr; }
}
/* THEME-AWARE CONTRAST FIX (light + dark) */
.quiz-question{color:var(--fg);}
.quiz-sub{color:var(--fg-mid);}
.quiz-option{background:var(--bg-card);color:var(--fg);}
.quiz-option-title{color:var(--fg);}
.quiz-option-desc{color:var(--fg-mid);}
.quiz-option.selected .quiz-option-title{color:var(--accent-text);}
.quiz-progress-dot.active{background:var(--fg);}
.quiz-back{color:var(--fg-mid);}
.quiz-back:hover{color:var(--fg);}
.result-time-block{background:linear-gradient(var(--bg-card),var(--bg-card)),var(--bg);}
.result-time-label{color:var(--accent-text);}
.result-step{border-bottom:1px solid var(--border);}
.result-step:hover{background:var(--bg-alt);}
.result-step-label{color:var(--fg-mid);}
.result-step-product{color:var(--fg);}
.result-retake{color:var(--fg-mid);border-color:var(--border);}
.result-retake:hover{color:var(--fg);}

/* ── ADDED 2026-08-11 (E5): multi-select, ranking, trust microcopy,
      diagnosis summary, reasons, gaps. Reuses the existing token set and
      component grammar — no new colours, type scale or spacing values. ── */
.quiz-option[aria-pressed="true"] .quiz-rank{opacity:1;}
.quiz-rank{
  display:inline-flex;align-items:center;justify-content:center;
  width:20px;height:20px;flex-shrink:0;
  border:1px solid var(--bronze);color:var(--accent-text);
  font-family:var(--font-display);font-size:9px;font-weight:700;
  opacity:0;transition:opacity .2s;
}
.quiz-option-head{display:flex;align-items:center;gap:10px;}
.quiz-why{
  margin-top:-32px;margin-bottom:36px;
}
.quiz-why-toggle{
  background:none;border:none;padding:0;cursor:pointer;
  font-family:var(--font-display);font-size:9px;font-weight:700;
  letter-spacing:.2em;text-transform:uppercase;color:var(--fg-mid);
  border-bottom:1px solid var(--border);
}
.quiz-why-toggle:hover{color:var(--fg);}
.quiz-why-body{
  display:none;margin-top:12px;max-width:520px;
  font-family:var(--font-body);font-size:13px;line-height:1.6;color:var(--fg-mid);
}
.quiz-why-body.open{display:block;}
.quiz-hint{
  font-family:var(--font-body);font-size:12px;color:var(--fg-mid);
  margin-top:-28px;margin-bottom:32px;
}
.result-summary{
  border:1px solid var(--border);background:var(--bg-card);
  padding:28px 32px;margin-bottom:48px;
}
.result-summary-title{
  font-family:var(--font-display);font-size:9px;font-weight:700;
  letter-spacing:.28em;text-transform:uppercase;
  /* Theme-aware by necessity, both values measured: --bronze on the light card
     is 2.3:1 (fails AA) while --accent-deep on the dark card is 3.12:1 (also
     fails). Neither token passes in both modes, so each mode takes the one that
     does — light 4.51:1, dark 6.19:1.
     RESOLVED 2026-08-18. .result-time-label used --bronze in both modes and carried the
     light-mode failure at 2.30:1 — on the RESULT screen, which axe has never reached because
     it does not exist until seven questions are answered. So K12's "0 across 36 pages" was
     true of what axe saw and false of the site. It now uses --accent-text (4.76:1 light,
     already compliant dark). The earlier "deliberately NOT changed: experience-changing"
     reasoning is superseded by the Founder's 2026-08-18 ruling that K12 is
     IMPLEMENTATION-ONLY under rule 15's accessibility clause. */
  color:var(--accent-deep);
  margin-bottom:16px;
}
html[data-mode="dark"] .result-summary-title{ color:var(--bronze); }
.result-summary-list{display:flex;flex-wrap:wrap;gap:8px;}
.result-chip{
  border:1px solid var(--border);padding:6px 12px;
  font-family:var(--font-display);font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--fg);
}
.result-step-why{
  font-family:var(--font-body);font-size:12px;line-height:1.5;
  color:var(--fg-mid);margin-top:6px;
}
.result-step-meta{
  font-family:var(--font-body);font-size:11px;line-height:1.5;
  color:var(--fg-mid);margin-top:6px;font-style:italic;
}
.result-note{
  border:1px solid var(--border);border-left:2px solid var(--bronze);
  padding:20px 24px;margin-bottom:24px;
  font-family:var(--font-body);font-size:13px;line-height:1.65;color:var(--fg-mid);
}
.result-note strong{color:var(--fg);font-weight:600;}
@media (max-width:680px){
  .quiz-why{margin-top:-24px;}
  .result-summary{padding:22px 20px;}
}
</style>
@endverbatim
@endpush

@push('pre_scripts')
@verbatim
<script>
/* The quiz engine and its model are untouched; only the product lookup moved.
   This page used to load the whole legacy data-lite.js bundle for one thing:
   resolving a recommended product id to its slug for the result links. It now
   reads the catalog over /api/products instead, so a deactivated or renamed
   product cannot linger here after it has left the shop.

   The fetch is async, and that is safe: KOTIVA.getById is only ever called
   from build(), which cannot run until the visitor has answered seven
   questions. slugOf() already falls back to /shop when a lookup misses, so a
   slow or failed request degrades to the shop link rather than a dead one. */
(function () {
  'use strict';
  var byId = {};
  window.KOTIVA = window.KOTIVA || {};
  window.KOTIVA.products = [];
  window.KOTIVA.getById = function (id) { return byId[id] || null; };

  fetch('/api/products', { headers: { 'Accept': 'application/json' } })
    .then(function (res) { return res.ok ? res.json() : { data: [] }; })
    .then(function (payload) {
      var rows = (payload && payload.data) || [];
      window.KOTIVA.products = rows;
      rows.forEach(function (p) { byId[p.id] = p; });
    })
    .catch(function () { /* result links fall back to /shop */ });
})();
</script>
@endverbatim
@endpush

@push('pre_scripts')
<script src="{{ asset('js/routine-model.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush

@push('pre_scripts')
<script src="{{ asset('js/routine-engine.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush

@section('content')

  <!-- HERO -->
  <header class="rf-hero">
    <div class="container">
      <p class="t-eyebrow reveal" style="margin-bottom:16px;">Personalised Skincare</p>
      <h1 class="t-display reveal reveal-delay-1" style="max-width:800px; margin-bottom:24px;">FIND YOUR<br>ROUTINE</h1>
      <span class="t-script reveal reveal-delay-2" style="display:block; margin-bottom:24px;">Tailored for you.</span>
      <p class="t-body reveal reveal-delay-3" style="max-width:520px;">Answer 7 quick questions about your skin and we'll build a complete morning and evening routine using the right Kotiva products — in the right order.</p>
    </div>
  </header>

  <!-- QUIZ -->
  <section class="quiz-section" id="quiz-section">
    <div class="container">
      <div class="quiz-wrap">
        <!-- PROGRESS -->
        <div class="quiz-progress">
          <div class="quiz-progress-dot active" id="pdot-0"></div>
          <div class="quiz-progress-dot" id="pdot-1"></div>
          <div class="quiz-progress-dot" id="pdot-2"></div>
          <div class="quiz-progress-dot" id="pdot-3"></div>
          <div class="quiz-progress-dot" id="pdot-4"></div>
          <div class="quiz-progress-dot" id="pdot-5"></div>
          <div class="quiz-progress-dot" id="pdot-6"></div>
        </div>

        <!-- STEP 1: ZONE -->
        <div class="quiz-step active" id="step-0" data-field="zones" data-multi="true">
          <div class="quiz-step-label">Step 01 of 07 — Where</div>
          <h2 class="quiz-question">WHAT WOULD YOU<br>LIKE A ROUTINE FOR?</h2>
          <p class="quiz-sub">Choose one or more. Kotiva's range covers face, body and hair.</p>
          <div class="quiz-options" style="grid-template-columns:repeat(3,1fr);">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="face">
              <span class="quiz-option-head"><span class="quiz-option-title">Face</span></span>
              <span class="quiz-option-desc">Cleansing, treatment, moisture and protection</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="body">
              <span class="quiz-option-head"><span class="quiz-option-title">Body</span></span>
              <span class="quiz-option-desc">Hands, underarms and body care</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="hair">
              <span class="quiz-option-head"><span class="quiz-option-title">Hair</span></span>
              <span class="quiz-option-desc">Scalp and hair-fall care</span>
            </button>
          </div>
          <div class="quiz-nav"><span></span>
            <button class="btn btn-primary" type="button" data-next id="next-0" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 2: SKIN TYPE -->
        <div class="quiz-step" id="step-1" data-field="skinType">
          <div class="quiz-step-label">Step 02 of 07 — Skin Type</div>
          <h2 class="quiz-question">WHAT IS YOUR<br>SKIN TYPE?</h2>
          <p class="quiz-sub">Select the option that best describes how your skin normally feels by midday.</p>
          <div class="quiz-options">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="oily">
              <span class="quiz-option-head"><span class="quiz-option-title">Oily</span></span>
              <span class="quiz-option-desc">Shiny by midday, enlarged pores, prone to breakouts</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="dry">
              <span class="quiz-option-head"><span class="quiz-option-title">Dry</span></span>
              <span class="quiz-option-desc">Tight, flaky, or rough — especially after cleansing</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="combination">
              <span class="quiz-option-head"><span class="quiz-option-title">Combination</span></span>
              <span class="quiz-option-desc">Oily T-zone but dry or normal cheeks</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="balanced">
              <span class="quiz-option-head"><span class="quiz-option-title">Balanced</span></span>
              <span class="quiz-option-desc">Comfortable and even, minimal shine</span>
            </button>
          </div>
          <div class="quiz-why">
            <button class="quiz-why-toggle" type="button" data-why>Why we ask this</button>
            <div class="quiz-why-body">Your skin type decides which cleanser and sun protection suit you — Kotiva makes different formulas for oily and combination skin than for dry or balanced skin. Sensitivity is asked separately on the next step, because skin can be oily <em>and</em> reactive at the same time.</div>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-primary" type="button" data-next id="next-1" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 3: SENSITIVITY -->
        <div class="quiz-step" id="step-2" data-field="sensitive">
          <div class="quiz-step-label">Step 03 of 07 — Sensitivity</div>
          <h2 class="quiz-question">DOES YOUR SKIN<br>REACT EASILY?</h2>
          <p class="quiz-sub">Redness, stinging or discomfort with new products.</p>
          <div class="quiz-options">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="yes">
              <span class="quiz-option-head"><span class="quiz-option-title">Yes, it reacts</span></span>
              <span class="quiz-option-desc">I notice redness, tingling or irritation fairly easily</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="no">
              <span class="quiz-option-head"><span class="quiz-option-title">No, it tolerates well</span></span>
              <span class="quiz-option-desc">I can use most products without a reaction</span>
            </button>
          </div>
          <div class="quiz-why">
            <button class="quiz-why-toggle" type="button" data-why>Why we ask this</button>
            <div class="quiz-why-body">We ask this separately from your skin type so the two can be combined. If your skin reacts easily, we favour the formulas Kotiva describes as suitable for sensitive skin, and we follow Kotiva's own guidance on easing into their exfoliating serum. This is a preference filter, not medical advice — if you have a diagnosed skin condition, or you are pregnant or breastfeeding, please speak to your doctor or pharmacist.</div>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-primary" type="button" data-next id="next-2" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 4: CONCERNS (multi, ranked) -->
        <div class="quiz-step" id="step-3" data-field="concerns" data-multi="true" data-max="3">
          <div class="quiz-step-label">Step 04 of 07 — Your Concerns</div>
          <h2 class="quiz-question">WHAT WOULD YOU<br>MOST LIKE TO CHANGE?</h2>
          <p class="quiz-sub">Choose up to three, in order of importance. The first one you pick counts most.</p>
          <div class="quiz-options" id="opts-concerns"></div>
          <p class="quiz-hint" id="concern-hint"></p>
          <div class="quiz-why">
            <button class="quiz-why-toggle" type="button" data-why>Why we ask this</button>
            <div class="quiz-why-body">Ranking matters: where two Kotiva products could fill the same step, we choose the one that answers your first concern. If nothing in the range addresses something you picked, we tell you plainly on your results rather than substituting something unrelated.</div>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-primary" type="button" data-next id="next-3" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 5: ROUTINE SIZE -->
        <div class="quiz-step" id="step-4" data-field="routineSize">
          <div class="quiz-step-label">Step 05 of 07 — Routine Size</div>
          <h2 class="quiz-question">HOW MANY STEPS<br>SUIT YOUR DAY?</h2>
          <p class="quiz-sub">A routine you actually keep beats a longer one you abandon.</p>
          <div class="quiz-options" style="grid-template-columns:repeat(3,1fr);">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="simple">
              <span class="quiz-option-head"><span class="quiz-option-title">Simple</span></span>
              <span class="quiz-option-desc">The essentials only — cleanse, moisturise, protect</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="essentials">
              <span class="quiz-option-head"><span class="quiz-option-title">Balanced</span></span>
              <span class="quiz-option-desc">The essentials plus one targeted step</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="full">
              <span class="quiz-option-head"><span class="quiz-option-title">Complete</span></span>
              <span class="quiz-option-desc">The full ritual, every step Kotiva offers you</span>
            </button>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-primary" type="button" data-next id="next-4" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 6: EXPERIENCE -->
        <div class="quiz-step" id="step-5" data-field="experience">
          <div class="quiz-step-label">Step 06 of 07 — Experience</div>
          <h2 class="quiz-question">HOW EXPERIENCED<br>ARE YOU?</h2>
          <p class="quiz-sub">This decides whether we include Kotiva's stronger exfoliating formulas.</p>
          <div class="quiz-options" style="grid-template-columns:repeat(3,1fr);">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="beginner">
              <span class="quiz-option-head"><span class="quiz-option-title">New to Skincare</span></span>
              <span class="quiz-option-desc">Just starting out, building a first routine</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="intermediate">
              <span class="quiz-option-head"><span class="quiz-option-title">Some Experience</span></span>
              <span class="quiz-option-desc">Have a routine, ready to add actives</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="advanced">
              <span class="quiz-option-head"><span class="quiz-option-title">Experienced</span></span>
              <span class="quiz-option-desc">Familiar with acids and stronger formulas</span>
            </button>
          </div>
          <div class="quiz-why">
            <button class="quiz-why-toggle" type="button" data-why>Why we ask this</button>
            <div class="quiz-why-body">Two Kotiva products combine several exfoliating acids. If you are new to skincare we leave them out, and we never place two acid formulas in the same routine. Kotiva's own instructions for the Pores Off Serum describe easing in gradually, and we pass that guidance on to you as written.</div>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-primary" type="button" data-next id="next-5" disabled>Next &rarr;</button>
          </div>
        </div>

        <!-- STEP 7: TEXTURE -->
        <div class="quiz-step" id="step-6" data-field="texture">
          <div class="quiz-step-label">Step 07 of 07 — Preference</div>
          <h2 class="quiz-question">HOW DO YOU LIKE<br>TO CLEANSE?</h2>
          <p class="quiz-sub">Kotiva makes several cleansers. If you have a preference, we'll honour it.</p>
          <div class="quiz-options">
            <button class="quiz-option" type="button" aria-pressed="false" data-value="foam">
              <span class="quiz-option-head"><span class="quiz-option-title">Foam</span></span>
              <span class="quiz-option-desc">Light, airy lather from a pump</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="gel">
              <span class="quiz-option-head"><span class="quiz-option-title">Gel</span></span>
              <span class="quiz-option-desc">Fresh, clarifying, rinses clean</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="cream">
              <span class="quiz-option-head"><span class="quiz-option-title">Cream</span></span>
              <span class="quiz-option-desc">Softer and more cushioned on the skin</span>
            </button>
            <button class="quiz-option" type="button" aria-pressed="false" data-value="">
              <span class="quiz-option-head"><span class="quiz-option-title">No preference</span></span>
              <span class="quiz-option-desc">Choose whatever suits my skin best</span>
            </button>
          </div>
          <div class="quiz-nav">
            <button class="quiz-back" type="button" data-back>&larr; Back</button>
            <button class="btn btn-bronze" type="button" id="build-btn" disabled>Build My Routine &rarr;</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- RESULT -->
  <section class="result-section" id="result-section">
    <div class="container">
      <div class="result-header reveal">
        <p class="t-eyebrow" style="text-align:center; margin-bottom:16px;">Your Personalised Routine</p>
        <h2 class="t-headline" style="text-align:center; margin-bottom:12px;" id="result-title">YOUR KOTIVA ROUTINE</h2>
        <p class="t-body" style="text-align:center;" id="result-subtitle">Tailored for your skin. In the right order.</p>
      </div>

      <div class="result-summary" id="result-summary" style="margin-top:48px;"></div>

      <div class="result-routine-grid" id="result-grid"></div>

      <div id="result-notes"></div>

      <div class="result-actions">
        <a href="{{ route('shop.index') }}" class="btn btn-primary" id="shop-routine-cta">Shop These Products</a>
        <a href="{{ route('shop.index') }}" class="btn btn-outline">View Full Range</a>
      </div>
      <div style="text-align:center; margin-top:28px;">
        <button class="result-retake" onclick="retakeQuiz()">← Retake the Quiz</button>
      </div>
    </div>
  </section>

@endsection

@push('scripts')
@verbatim
<script>
/* ── ROUTINE FINDER CONTROLLER (E5 rebuild, 2026-08-11) ──────────────
   State only. All recommendation logic lives in js/routine-engine.js, and
   all product knowledge in js/routine-model.js (generated from
   data/routine-model.json). This file decides nothing about products. */
(function () {
  'use strict';
  var MODEL = window.KOTIVA_ROUTINE_MODEL, ENGINE = window.KOTIVA_ENGINE;
  var steps = [].slice.call(document.querySelectorAll('.quiz-step'));
  var state = { zones: [], skinType: null, sensitive: null, concerns: [],
                routineSize: null, experience: null, texture: null };
  var answered = {}, cur = 0;

  /* ---- concerns are rendered from the model, filtered by chosen zones ---- */
  function renderConcerns() {
    var wrap = document.getElementById('opts-concerns');
    if (!wrap) return;
    var zones = state.zones.length ? state.zones : ['face'];
    var opts = MODEL.concerns.options.filter(function (o) {
      return o.zones.some(function (z) { return zones.indexOf(z) !== -1; });
    });
    wrap.innerHTML = opts.map(function (o) {
      return '<button class="quiz-option" type="button" aria-pressed="false" data-value="' + o.id + '">' +
             '<span class="quiz-option-head"><span class="quiz-rank"></span>' +
             '<span class="quiz-option-title">' + o.label + '</span></span></button>';
    }).join('');
    state.concerns = state.concerns.filter(function (c) {
      return opts.some(function (o) { return o.id === c; });
    });
    paintConcerns();
  }

  function paintConcerns() {
    var wrap = document.getElementById('opts-concerns');
    if (!wrap) return;
    [].forEach.call(wrap.querySelectorAll('.quiz-option'), function (b) {
      var i = state.concerns.indexOf(b.dataset.value);
      b.classList.toggle('selected', i !== -1);
      b.setAttribute('aria-pressed', i !== -1 ? 'true' : 'false');
      b.querySelector('.quiz-rank').textContent = i === -1 ? '' : (i + 1);
    });
    var n = state.concerns.length;
    document.getElementById('concern-hint').textContent =
      n === 0 ? 'Pick at least one.' :
      n === 3 ? 'Three selected — deselect one to change your order.' :
                n + ' selected. You may pick ' + (3 - n) + ' more.';
    answered.concerns = n > 0;
  }

  /* ---- generic option handling ---- */
  document.querySelector('.quiz-wrap').addEventListener('click', function (e) {
    var why = e.target.closest('[data-why]');
    if (why) { why.parentNode.querySelector('.quiz-why-body').classList.toggle('open'); return; }
    if (e.target.closest('#build-btn')) { build(); return; }
    var next = e.target.closest('[data-next]');
    if (next) { go(cur + 1); return; }
    var back = e.target.closest('[data-back]');
    if (back) { go(cur - 1); return; }

    var btn = e.target.closest('.quiz-option');
    if (!btn) return;
    var step = btn.closest('.quiz-step');
    var field = step.dataset.field;
    var val = btn.dataset.value;

    if (step.dataset.multi === 'true') {
      var list = field === 'concerns' ? state.concerns : state.zones;
      var i = list.indexOf(val);
      if (i !== -1) list.splice(i, 1);
      else {
        var max = parseInt(step.dataset.max || '99', 10);
        if (list.length >= max) return;
        list.push(val);
      }
      if (field === 'concerns') paintConcerns();
      else {
        [].forEach.call(step.querySelectorAll('.quiz-option'), function (b) {
          var on = state.zones.indexOf(b.dataset.value) !== -1;
          b.classList.toggle('selected', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        answered.zones = state.zones.length > 0;
      }
    } else {
      [].forEach.call(step.querySelectorAll('.quiz-option'), function (b) {
        b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false');
      });
      btn.classList.add('selected'); btn.setAttribute('aria-pressed', 'true');
      state[field] = field === 'sensitive' ? (val === 'yes') : (val === '' ? null : val);
      answered[field] = true;   /* "No preference" is a real answer */
    }
    syncNav();
  });

  function syncNav() {
    var step = steps[cur], field = step.dataset.field;
    var btn = step.querySelector('[data-next]') || step.querySelector('#build-btn');
    if (btn) btn.disabled = !answered[field];
  }

  function go(n) {
    if (n < 0 || n >= steps.length) return;
    if (n > cur && !answered[steps[cur].dataset.field]) return;
    steps[cur].classList.remove('active');
    document.getElementById('pdot-' + cur).classList.remove('active');
    if (n > cur) document.getElementById('pdot-' + cur).classList.add('done');
    else document.getElementById('pdot-' + n).classList.remove('done');
    cur = n;
    steps[cur].classList.add('active');
    document.getElementById('pdot-' + cur).classList.add('active');
    if (steps[cur].dataset.field === 'concerns') renderConcerns();
    syncNav();
    var sec = document.getElementById('quiz-section');
    window.scrollTo({ top: sec.offsetTop - 80, behavior: 'smooth' });
  }

  /* ---- result ---- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function slugOf(id) {
    var p = (window.KOTIVA && KOTIVA.getById) ? KOTIVA.getById(id) : null;
    return p && p.slug ? p.slug : null;
  }
  var STEP_LABEL = {
    'makeup-removal': 'Remove', 'cleanser': 'Cleanse', 'toner': 'Tone',
    'treatment': 'Treat', 'moisturizer': 'Moisturise', 'spf': 'Protect',
    'hand-care': 'Hands', 'deodorant': 'Underarms',
    'intimate-cleanser': 'Intimate cleanse', 'intimate-care': 'Intimate care',
    'hair-cleanser': 'Wash', 'hair-treatment': 'Treat'
  };

  function renderSteps(list, title, icon) {
    if (!list.length) return '';
    return '<div class="result-time-block"><div class="result-time-label">' +
      '<div class="result-time-icon">' + icon + '</div>' + esc(title) + '</div>' +
      '<div class="result-steps">' + list.map(function (s, i) {
        var slug = slugOf(s.id);
        var href = slug ? '/product/' + slug : '/shop';
        return '<a class="result-step" href="' + href + '">' +
          '<div class="result-step-num">' + ('0' + (i + 1)).slice(-2) + '</div><div>' +
          '<div class="result-step-label">' + esc(STEP_LABEL[s.step] || s.step) + '</div>' +
          '<div class="result-step-product">' + esc(s.name) + '</div>' +
          '<div class="result-step-why">' + esc(s.reason) + '</div>' +
          (s.frequency ? '<div class="result-step-meta">' + esc(s.frequency) + '</div>' : '') +
          (s.positioning ? '<div class="result-step-meta">' + esc(s.positioning) + '</div>' : '') +
          (s.cautions && s.cautions.length ? '<div class="result-step-meta">' + esc(s.cautions.join(' ')) + '</div>' : '') +
          '</div></a>';
      }).join('') + '</div></div>';
  }

  function build() {
    var r = ENGINE.build(state, MODEL);

    var typeLabel = state.skinType ? state.skinType.charAt(0).toUpperCase() + state.skinType.slice(1) : '';
    document.getElementById('result-title').textContent =
      typeLabel ? typeLabel.toUpperCase() + ' SKIN ROUTINE' : 'YOUR KOTIVA ROUTINE';
    document.getElementById('result-subtitle').textContent =
      'Built from what you told us, using Kotiva’s own product guidance.';

    /* what we heard — the reasoning is shown, not implied */
    var chips = [];
    state.zones.forEach(function (z) { chips.push(z.charAt(0).toUpperCase() + z.slice(1)); });
    if (typeLabel) chips.push(typeLabel + ' skin');
    if (state.sensitive) chips.push('Reacts easily');
    state.concerns.forEach(function (c, i) {
      var o = MODEL.concerns.options.filter(function (x) { return x.id === c; })[0];
      chips.push((i + 1) + '. ' + (o ? o.label : c));
    });
    if (state.routineSize) chips.push(state.routineSize.charAt(0).toUpperCase() + state.routineSize.slice(1) + ' routine');
    document.getElementById('result-summary').innerHTML =
      '<div class="result-summary-title">What we based this on</div><div class="result-summary-list">' +
      chips.map(function (c) { return '<span class="result-chip">' + esc(c) + '</span>'; }).join('') + '</div>';

    document.getElementById('result-grid').innerHTML =
      renderSteps(r.am, 'Morning', '☀') +
      renderSteps(r.pm, 'Evening', '◑') +
      renderSteps(r.anytime, 'Body & Hair', '○');

    /* unmet concerns and add-ons — stated plainly rather than left silent */
    var notes = [];
    r.gaps.forEach(function (g) {
      var o = MODEL.concerns.options.filter(function (x) { return x.id === g.concern; })[0];
      var label = o ? o.label.toLowerCase() : g.concern;
      var msg;
      if (g.reason === 'sensitivity') {
        msg = 'You told us your skin reacts easily, and Kotiva’s products for <strong>' + esc(label) +
              '</strong> contain exfoliating acids. We have left them out rather than recommend something that may not suit you. Your pharmacist or doctor can advise if you would like to try them.';
      } else if (g.reason === 'actives-experience') {
        msg = 'Kotiva’s products for <strong>' + esc(label) + '</strong> combine several exfoliating acids. ' +
              'As you are new to skincare we have left them out for now — worth revisiting once your routine is established.';
      } else {
        msg = 'We could not match <strong>' + esc(label) + '</strong> to a product in Kotiva’s current range, ' +
              'so we have not put anything in its place.';
      }
      notes.push('<div class="result-note">' + msg + '</div>');
    });
    r.addons.forEach(function (a) {
      notes.push('<div class="result-note">You may also want <strong>' + esc(a.name) + '</strong> — ' + esc(a.note) + '</div>');
    });
    document.getElementById('result-notes').innerHTML = notes.join('');

    /* carry the recommendation through to the shop (K25 / S2-4).
       Slugs, not ids: the shop's cards are matched on their own href, so the
       handoff needs no product data loaded on that page. */
    var slugs = [];
    [r.am, r.pm, r.anytime].forEach(function (half) {
      half.forEach(function (s) {
        var sl = slugOf(s.id);
        if (sl && slugs.indexOf(sl) === -1) slugs.push(sl);
      });
    });
    var cta = document.getElementById('shop-routine-cta');
    if (cta && slugs.length) cta.setAttribute('href', '/shop?routine=' + slugs.join(','));

    document.getElementById('quiz-section').style.display = 'none';
    var sec = document.getElementById('result-section');
    sec.classList.add('visible');
    window.scrollTo({ top: sec.offsetTop - 80, behavior: 'smooth' });
  }

  window.retakeQuiz = function () {
    state = { zones: [], skinType: null, sensitive: null, concerns: [],
              routineSize: null, experience: null, texture: null };
    answered = {};
    [].forEach.call(document.querySelectorAll('.quiz-option'), function (b) {
      b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false');
    });
    steps.forEach(function (s, i) { s.classList.toggle('active', i === 0); });
    [].forEach.call(document.querySelectorAll('.quiz-progress-dot'), function (d, i) {
      d.classList.toggle('active', i === 0); d.classList.remove('done');
    });
    cur = 0; syncNav();
    document.getElementById('quiz-section').style.display = '';
    document.getElementById('result-section').classList.remove('visible');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  renderConcerns();
  syncNav();
})();
</script>
@endverbatim
@endpush
