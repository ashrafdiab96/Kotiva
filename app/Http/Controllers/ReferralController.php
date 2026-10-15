<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use App\Support\Referral;
use App\Support\Salla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * /dr/{code}: a doctor's share link (D-38).
 *
 * Records the visit, sets the kotiva_ref cookie and sends the patient to the
 * partner's landing page — or, with ?to=shop, straight to the Salla store with
 * the affiliate query already on it. Every answer is a 302 so a browser never
 * caches the hop and every click is counted.
 */
final class ReferralController extends Controller
{
    public function enter(Request $request, string $code): RedirectResponse
    {
        $code = strtolower($code);

        $partner = ReferralPartner::isValidCode($code)
            ? ReferralPartner::query()->active()->where('code', $code)->first()
            : null;

        // A mistyped or retired code lands on the home page quietly: no 404 a
        // patient would read as a broken link, and no cookie.
        if (! $partner instanceof ReferralPartner) {
            return redirect('/', 302);
        }

        $this->recordVisit($request, $partner);

        // Last doctor wins: this cookie replaces any earlier one, and the
        // redirect below must already carry this partner rather than the one
        // the browser arrived with.
        Referral::remember($request, $partner);

        $target = $request->query('to') === 'shop'
            ? redirect()->away(Salla::storeUrl(), 302)
            : redirect($partner->safeLandingPath(), 302);

        return $target->withCookie(Cookie::make(
            Referral::cookieName(),
            $partner->code,
            Referral::cookieMinutes(),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    private function recordVisit(Request $request, ReferralPartner $partner): void
    {
        $now = now();
        $ipHash = ReferralVisit::hashIp($request->ip());

        $seenToday = ReferralVisit::query()
            ->where('referral_partner_id', $partner->id)
            ->where('ip_hash', $ipHash)
            ->whereBetween('visited_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->exists();

        $userAgent = $request->userAgent();
        $referer = $request->headers->get('referer');

        ReferralVisit::create([
            'referral_partner_id' => $partner->id,
            'visited_at' => $now,
            'ip_hash' => $ipHash,
            'user_agent' => is_string($userAgent) && $userAgent !== '' ? Str::limit($userAgent, 512, '') : null,
            'referer' => is_string($referer) && $referer !== '' ? Str::limit($referer, 2048, '') : null,
            'landing_path' => $request->query('to') === 'shop' ? 'salla:store' : $partner->safeLandingPath(),
            'is_unique_day' => ! $seenToday,
        ]);
    }
}
