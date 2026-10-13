<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * The content pages, and only those.
 *
 * Since the Salla handoff, /shop and /product/* are redirects to the Salla
 * store; listing a redirect in a sitemap tells a crawler the page is
 * canonical here when it is not. The store publishes its own sitemap.
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
        $origin = (string) config('app.url');

        $urls = [];

        foreach (self::PAGE_PRIORITIES as $routeName => $priority) {
            $urls[] = [
                'loc' => $origin.$this->pathFor($routeName),
                'lastmod' => $this->lastModified($routeName),
                'priority' => $priority,
            ];
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * The date the page's own template last changed — the honest "last
     * modified" for hand-authored content. Null (and so no <lastmod>) when the
     * template cannot be located, rather than a guess.
     */
    private function lastModified(string $routeName): ?string
    {
        try {
            $path = View::getFinder()->find('pages.'.$routeName);
        } catch (InvalidArgumentException) {
            return null;
        }

        $mtime = @filemtime($path);

        return $mtime === false ? null : Carbon::createFromTimestamp($mtime)->toDateString();
    }

    private function pathFor(string $routeName): string
    {
        $path = parse_url(route($routeName), PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }
}
