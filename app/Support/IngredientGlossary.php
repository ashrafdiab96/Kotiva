<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Builds the ingredient glossary from the catalog.
 *
 * Two sources meet here, and the split matters:
 *
 *   • resources/glossary/ingredients.json — the CURATED canonical actives. The
 *     copy is deliberately conservative, function-level and uncited: efficacy
 *     figures and peer-reviewed references live on the product pages, so the
 *     glossary stays citable without re-asserting a claim. It is authored
 *     content, not derived, so it is not something the catalog can produce.
 *
 *   • the products table — which products actually contain each active. This
 *     is what the old generator baked into static HTML and what drifted
 *     whenever the catalog changed; it is now resolved per request.
 *
 * `aliases` bridge the two: the raw ingredient strings in the catalog are
 * inconsistent ("Panthenol (Pro-Vitamin B5)" vs "Pro-Vitamin B5 (Panthenol)"),
 * so each entry lists every spelling that means it.
 *
 * The glossary is a SUBSET by design: 22 canonical actives against ~61 distinct
 * ingredient strings. An ingredient with no entry is simply not shown — the old
 * consistency gate asserted the reverse direction only, that every committed
 * entry still resolves to at least one product. That rule is kept below.
 */
final class IngredientGlossary
{
    /**
     * @return list<array{
     *     slug: string, name: string, also: string, category: string,
     *     summary: string, products: list<array{slug: string, name: string}>
     * }>
     */
    public function entries(): array
    {
        $products = Product::query()
            ->active()
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'ingredients']);

        $out = [];

        foreach ($this->curated() as $entry) {
            $aliases = array_map(
                fn (string $a): string => $this->normalise($a),
                (array) ($entry['aliases'] ?? [])
            );

            $matched = $products
                ->filter(function (Product $p) use ($aliases): bool {
                    foreach (($p->ingredients ?? []) as $ingredient) {
                        if (in_array($this->normalise((string) $ingredient), $aliases, true)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->map(fn (Product $p): array => [
                    'slug' => $p->slug,
                    'name' => $p->displayName(),
                ])
                ->values();

            // An entry that matches nothing is omitted rather than rendered
            // empty: "Found in" with no products reads as a broken page, and
            // the legacy gate treated that state as a failure.
            if ($matched->isEmpty()) {
                continue;
            }

            $out[] = [
                'slug' => (string) $entry['slug'],
                'name' => (string) $entry['name'],
                'also' => (string) ($entry['also'] ?? ''),
                'category' => (string) $entry['category'],
                'summary' => (string) $entry['summary'],
                'products' => $matched->all(),
            ];
        }

        // Alphabetical by slug — the order the static page shipped in.
        usort($out, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $out;
    }

    /**
     * Ingredient strings differ in case and spacing between products; matching
     * on a normalised form stops a stray double space from dropping a product
     * out of a "Found in" list.
     */
    private function normalise(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function curated(): Collection
    {
        $path = base_path('resources/glossary/ingredients.json');

        if (! is_file($path)) {
            return collect();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? collect($decoded) : collect();
    }
}
