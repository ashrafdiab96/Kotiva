<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ReferralPartnerResource;
use App\Models\Admin;
use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * "Doctor referrals — last 30 days": visits to /dr/<code> links (D-38).
 *
 * Not gated on kotiva.commerce_enabled like the order widgets: referral
 * visits are this site's own data. Orders and commission per doctor are in
 * Salla's Affiliate marketing dashboard, which the card says.
 */
final class ReferralsOverview extends Widget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.referrals-overview';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof Admin && $user->canViewReports();
    }

    /**
     * @return array{total: int, unique: int, top: list<array{name: string, code: string, visits: int, unique: int, url: string}>}
     */
    public static function stats(int $days = 30): array
    {
        $since = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $totals = ReferralVisit::query()
            ->where('visited_at', '>=', $since)
            ->selectRaw('COUNT(*) as visits, SUM(CASE WHEN is_unique_day THEN 1 ELSE 0 END) as uniques')
            ->first();

        $top = ReferralPartner::query()
            ->withTrashed()
            ->withCount([
                'visits as window_visits' => fn ($q) => $q->where('visited_at', '>=', $since),
                'visits as window_unique' => fn ($q) => $q->where('visited_at', '>=', $since)->where('is_unique_day', true),
            ])
            ->whereHas('visits', fn ($q) => $q->where('visited_at', '>=', $since))
            ->orderByDesc('window_visits')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (ReferralPartner $p): array => [
                'name' => $p->name,
                'code' => $p->code,
                'visits' => (int) $p->getAttribute('window_visits'),
                'unique' => (int) $p->getAttribute('window_unique'),
                'url' => ReferralPartnerResource::getUrl('view', ['record' => $p]),
            ])
            ->values()
            ->all();

        return [
            'total' => (int) ($totals?->getAttribute('visits') ?? 0),
            'unique' => (int) ($totals?->getAttribute('uniques') ?? 0),
            'top' => $top,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return self::stats(30);
    }
}
