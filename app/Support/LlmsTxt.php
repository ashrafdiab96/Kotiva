<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders llms.txt from the catalog.
 *
 * The brand prose comes from resources/llms/llms.txt.stub, the page links from
 * the site's origin (config app.url) and every product link is that product's
 * Salla URL — so the file cannot drift from the store, and no domain is ever
 * written into a static file. Served at /llms.txt; kotiva:llms-txt prints or
 * exports the same text.
 */
final class LlmsTxt
{
    /**
     * @return array{text: string, count: int, unlinked: int}
     */
    public static function render(?string $origin = null): array
    {
        $main = rtrim($origin !== null && $origin !== '' ? $origin : (string) config('app.url'), '/');

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->active()
            ->with('category')
            ->orderBy('id')
            ->get();

        $lines = $products->map(function (Product $p): string {
            $tags = array_values(array_filter([$p->category?->name, $p->concern]));

            return sprintf(
                '  - [%s](%s)%s',
                $p->name,
                Salla::productUrl($p, withReferral: false),
                $tags === [] ? '' : ' — '.implode(', ', $tags)
            );
        });

        $text = strtr((string) file_get_contents(resource_path('llms/llms.txt.stub')), [
            '{{MAIN}}' => $main,
            '{{STORE}}' => Salla::storeUrl(withReferral: false),
            '{{COUNT}}' => (string) $products->count(),
            '{{PRODUCTS}}' => $lines->implode("\n"),
        ]);

        return [
            'text' => $text,
            'count' => $products->count(),
            'unlinked' => $products->filter(fn (Product $p): bool => ! Salla::isStorePath($p->salla_path))->count(),
        ];
    }
}
