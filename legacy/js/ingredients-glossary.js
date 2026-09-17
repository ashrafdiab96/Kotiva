/* ============================================================
   KOTIVA™ — Ingredient Glossary (curated canonical actives)
   W5 / AEO deliverable. One canonical entry per key active, so
   products link to a single reference entry instead of each
   product page re-describing the same ingredient.

   Descriptions are CONSERVATIVE, function-level statements of what
   each active does — the well-established dermatological role that
   the per-product `science` fields (data-full.js) state and cite in
   full. Efficacy figures + peer-reviewed references deliberately
   live on the product pages (already shipped in W4), NOT re-asserted
   here, to keep the glossary citable and non-over-claiming.

   `aliases` map the (inconsistent) raw ingredient-list strings in
   data-lite.js to their canonical active so "Found in" stays
   accurate. Source of truth for the underlying science:
   FILE-02_Product-SSoT.md via js/data-full.js.
   ============================================================ */

const KOTIVA_INGREDIENTS = [
  {
    "slug": "niacinamide",
    "name": "Niacinamide",
    "also": "Vitamin B3",
    "category": "Barrier & Tone",
    "summary": "A form of vitamin B3 that supports the skin barrier, helps regulate visible oil, and is widely used to even skin tone and calm the look of redness.",
    "aliases": ["Niacinamide"]
  },
  {
    "slug": "hyaluronic-acid",
    "name": "Hyaluronic Acid",
    "also": "Sodium Hyaluronate",
    "category": "Hydration",
    "summary": "A humectant that binds a large amount of water to the skin surface for immediate hydration and a smoother, plumper appearance, helping reduce moisture loss.",
    "aliases": ["Hyaluronic Acid"]
  },
  {
    "slug": "alpha-arbutin",
    "name": "Alpha Arbutin",
    "also": "",
    "category": "Brightening",
    "summary": "A gentle, stabilised brightening active that targets the look of uneven tone and dark spots by moderating excess melanin at the surface.",
    "aliases": ["Alpha Arbutin"]
  },
  {
    "slug": "allantoin",
    "name": "Allantoin",
    "also": "",
    "category": "Soothing & Repair",
    "summary": "A soothing, skin-conditioning agent that supports surface renewal and comfort, commonly used to calm and smooth compromised or rough-feeling skin.",
    "aliases": ["Allantoin"]
  },
  {
    "slug": "panthenol",
    "name": "Panthenol",
    "also": "Pro-Vitamin B5",
    "category": "Hydration",
    "summary": "The pro-vitamin form of B5 — a humectant and skin-conditioning agent that cushions against harsh cleansing, supports the barrier, and leaves skin soft and comfortable.",
    "aliases": ["Panthenol (Pro-Vitamin B5)", "Pro-Vitamin B5 (Panthenol)", "Panthenol (10%)"]
  },
  {
    "slug": "zinc-pca",
    "name": "Zinc PCA",
    "also": "",
    "category": "Oil Control",
    "summary": "A multifunctional zinc active for oily and blemish-prone skin that helps regulate visible sebum while providing surface antimicrobial support without over-drying.",
    "aliases": ["Zinc PCA"]
  },
  {
    "slug": "salicylic-acid",
    "name": "Salicylic Acid",
    "also": "BHA",
    "category": "Exfoliation",
    "summary": "An oil-soluble beta-hydroxy acid that works inside the pore to clear build-up and support a clearer, smoother-looking complexion in oily and blemish-prone skin.",
    "aliases": ["Salicylic Acid (BHA)"]
  },
  {
    "slug": "mandelic-acid",
    "name": "Mandelic Acid",
    "also": "AHA",
    "category": "Exfoliation",
    "summary": "A large-molecule alpha-hydroxy acid that exfoliates gently and evenly, making it well-suited to sensitive skin and the look of uneven tone.",
    "aliases": ["Mandelic Acid (AHA)"]
  },
  {
    "slug": "lactic-acid",
    "name": "Lactic Acid",
    "also": "AHA",
    "category": "Exfoliation",
    "summary": "An alpha-hydroxy acid that smooths surface texture and supports cell renewal while also acting as a humectant, so it exfoliates without stripping.",
    "aliases": ["Lactic Acid"]
  },
  {
    "slug": "azelaic-acid",
    "name": "Azelaic Acid",
    "also": "incl. Potassium Azeloyl Diglycinate",
    "category": "Tone & Blemishes",
    "summary": "A well-tolerated active that helps normalise the look of blemish-prone skin and even out tone; also used in its water-soluble derivative form (Potassium Azeloyl Diglycinate).",
    "aliases": ["Azelaic Acid", "Potassium Azeloyl Diglycinate"]
  },
  {
    "slug": "tranexamic-acid",
    "name": "Tranexamic Acid",
    "also": "",
    "category": "Brightening",
    "summary": "A targeted brightening active valued for addressing stubborn discolouration and the look of uneven tone, often paired with other brighteners.",
    "aliases": ["Tranexamic Acid"]
  },
  {
    "slug": "glutathione",
    "name": "Glutathione",
    "also": "",
    "category": "Brightening & Antioxidant",
    "summary": "An antioxidant tripeptide used to support a brighter, more even-looking complexion while helping defend the skin against everyday oxidative stress.",
    "aliases": ["Glutathione"]
  },
  {
    "slug": "baicapil",
    "name": "Baicapil™ Complex",
    "also": "",
    "category": "Hair",
    "summary": "A botanical scalp-and-hair complex used in anti-hair-loss formulas to support the look of density and healthier growth conditions at the root.",
    "aliases": ["Baicapil™ Complex"]
  },
  {
    "slug": "saw-palmetto",
    "name": "Saw Palmetto Extract",
    "also": "Serenoa Repens",
    "category": "Hair",
    "summary": "A botanical extract used in hair-thinning formulas to support a healthier scalp environment for growth.",
    "aliases": ["Saw Palmetto Extract"]
  },
  {
    "slug": "shea-butter",
    "name": "Shea Butter",
    "also": "Butyrospermum Parkii Butter",
    "category": "Nourishment",
    "summary": "A rich plant butter that nourishes and softens, reinforcing the skin's surface lipids for lasting comfort on dry and sensitive skin.",
    "aliases": ["Shea Butter (Butyrospermum Parkii Butter)", "Butyrospermum Parkii (Shea Butter)"]
  },
  {
    "slug": "sage-extract",
    "name": "Sage Extract",
    "also": "Salvia Officinalis",
    "category": "Antioxidant & Soothing",
    "summary": "A botanical extract with antioxidant and skin-calming properties, used to support comfort and a balanced-looking complexion.",
    "aliases": ["Salvia Officinalis (Sage) Extract"]
  },
  {
    "slug": "centella-asiatica",
    "name": "Centella Asiatica",
    "also": "Cica",
    "category": "Soothing & Repair",
    "summary": "A classic soothing botanical (\"cica\") used to calm the look of stressed or sensitised skin and support barrier comfort.",
    "aliases": ["Centella Asiatica Extract", "Centella Asiatica Root Extract"]
  },
  {
    "slug": "ceramides",
    "name": "Ceramides",
    "also": "EOP, NP, AP",
    "category": "Barrier",
    "summary": "Skin-identical lipids that help rebuild and reinforce the moisture barrier, reducing water loss and restoring resilience to dry, compromised skin.",
    "aliases": ["Ceramide EOP, NP, AP"]
  },
  {
    "slug": "green-tea",
    "name": "Green Tea Extract",
    "also": "Camellia Sinensis",
    "category": "Antioxidant",
    "summary": "A polyphenol-rich antioxidant botanical (a source of EGCG) used to help defend skin against oxidative stress and support a calmer, clearer look.",
    "aliases": ["Green Tea Extract", "Camellia Sinensis (Green Tea) Leaf Extract"]
  },
  {
    "slug": "vitamin-e",
    "name": "Vitamin E",
    "also": "Tocopherol",
    "category": "Antioxidant",
    "summary": "A fat-soluble antioxidant that helps protect the skin's surface lipids and supports softness and everyday environmental defence.",
    "aliases": ["Vitamin E", "Vitamin E (Tocopherol)", "Tocopherol (Vitamin E)"]
  },
  {
    "slug": "licorice-extract",
    "name": "Licorice Root Extract",
    "also": "Glycyrrhiza Glabra",
    "category": "Brightening & Soothing",
    "summary": "A botanical extract valued both for calming the look of redness and for its gentle brightening action on uneven tone.",
    "aliases": ["Glycyrrhiza (Licorice) Extract", "Glycyrrhiza Glabra (Licorice) Root Extract", "Licorice (Glycyrrhiza Glabra) Root Extract"]
  },
  {
    "slug": "rosemary-extract",
    "name": "Rosemary Extract",
    "also": "Rosmarinus Officinalis",
    "category": "Antioxidant & Soothing",
    "summary": "An aromatic botanical with antioxidant and skin-conditioning properties, used to support a calm, balanced-looking complexion and scalp.",
    "aliases": ["Rosmarinus Officinalis (Rosemary) Extract", "Rosmarinus Officinalis (Rosemary) Leaf Oil"]
  }
];

if (typeof window !== 'undefined') { window.KOTIVA_INGREDIENTS = KOTIVA_INGREDIENTS; }
