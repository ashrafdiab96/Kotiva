<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;

/**
 * The static sitemap.xml listed 35 hand-maintained URLs and drifted whenever a
 * product changed. This builds the same document from the catalog, so a
 * deactivated product leaves the sitemap the moment it leaves the shop.
 */
final class SitemapController extends Controller
{
    /**
     * Priorities carried over from the static sitemap so relative weighting
     * does not silently change.
     *
     * @var array<string, float>
     */
    private const PAGE_PRIORITIES = [
        'home' => 1.0,
        'shop.index' => 0.9,
        'routine-finder' => 0.8,
        'science' => 0.7,
        'ingredients' => 0.7,
        'about' => 0.6,
        'journal' => 0.6,
        'contact' => 0.5,
        'privacy-policy' => 0.3,
        'terms' => 0.3,
    ];

    public function __invoke(): Response
    {
        $origin = (string) config('kotiva.site_origin');

        $urls = [];

        foreach (self::PAGE_PRIORITIES as $routeName => $priority) {
            $urls[] = [
                'loc' => $origin.$this->pathFor($routeName),
                'lastmod' => null,
                'priority' => $priority,
            ];
        }

        Product::query()
            ->active()
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls, $origin): void {
                $urls[] = [
                    'loc' => $origin.'/product/'.$product->slug,
                    'lastmod' => $product->updated_at?->toDateString(),
                    'priority' => 0.7,
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function pathFor(string $routeName): string
    {
        $path = parse_url(route($routeName), PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }
}
