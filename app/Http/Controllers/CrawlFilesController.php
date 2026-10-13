<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\LlmsTxt;
use Illuminate\Http\Response;

/**
 * robots.txt and llms.txt, rendered from config.
 *
 * Both used to be static files in public/ with the domain written in. They are
 * routes now so the only place an origin is set is .env (APP_URL for this
 * site, KOTIVA_SALLA_STORE_URL for the store). public/robots.txt and
 * public/llms.txt must not come back: the web server would serve them ahead
 * of these routes.
 */
final class CrawlFilesController extends Controller
{
    public function robots(): Response
    {
        return response()
            ->view('crawl.robots', ['origin' => (string) config('app.url')])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms(): Response
    {
        return response(LlmsTxt::render()['text'], 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
