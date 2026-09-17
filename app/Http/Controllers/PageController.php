<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\IngredientGlossary;
use Illuminate\Contracts\View\View;

/**
 * The hand-authored content pages, ported 1:1 from the static site.
 *
 * These are deliberately thin: the pages are content, not behaviour. Where a
 * page needs catalog data — the home bestseller rail, the ingredient glossary —
 * it now comes from the database instead of a hand-maintained copy in the
 * markup or a build-time generator.
 */
final class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home', [
            // sort_order carries the rail's hand-authored sequence; see
            // ProductSeeder::HOME_RAIL_ORDER.
            'bestSellers' => Product::query()
                ->active()
                ->bestSellers()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),

            // The rail's header and terminus both printed hand-maintained
            // counts ("View All 25", "18 more products"). check-consistency.js
            // used to hold them true; with that gate retired they are derived.
            'catalogCount' => Product::query()->active()->count(),
        ]);
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function science(): View
    {
        return view('pages.science');
    }

    public function journal(): View
    {
        return view('pages.journal');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function ingredients(IngredientGlossary $glossary): View
    {
        return view('pages.ingredients', [
            'entries' => $glossary->entries(),
        ]);
    }

    public function routineFinder(): View
    {
        return view('pages.routine-finder');
    }

    public function privacyPolicy(): View
    {
        return view('pages.privacy-policy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }
}
