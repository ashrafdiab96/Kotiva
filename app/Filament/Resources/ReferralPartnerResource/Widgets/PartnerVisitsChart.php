<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Widgets;

use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * One partner's visits per day over the last 30 days, empty days included.
 */
final class PartnerVisitsChart extends ChartWidget
{
    public ?Model $record = null;

    protected static ?string $heading = 'Visits, last 30 days';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = $this->record instanceof ReferralPartner ? self::series($this->record, 30) : [];

        return [
            'datasets' => [
                [
                    'label' => 'Visits',
                    'data' => array_column($series, 'visits'),
                    'backgroundColor' => 'rgba(0, 93, 186, 0.35)',
                    'borderColor' => '#005DBA',
                ],
                [
                    'label' => 'Unique',
                    'data' => array_column($series, 'unique'),
                    'backgroundColor' => 'rgba(236, 119, 37, 0.45)',
                    'borderColor' => '#EC7725',
                ],
            ],
            'labels' => array_column($series, 'label'),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /**
     * @return list<array{date: string, label: string, visits: int, unique: int}>
     */
    public static function series(ReferralPartner $partner, int $days): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        // DATE() is the grouping function MySQL and SQLite share (D-28).
        $rows = ReferralVisit::query()
            ->where('referral_partner_id', $partner->id)
            ->where('visited_at', '>=', $start)
            ->selectRaw('DATE(visited_at) as day, COUNT(*) as visits, SUM(CASE WHEN is_unique_day THEN 1 ELSE 0 END) as uniques')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i);
            $row = $rows->get($date->format('Y-m-d'));

            $series[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('j M'),
                'visits' => (int) ($row->visits ?? 0),
                'unique' => (int) ($row->uniques ?? 0),
            ];
        }

        return $series;
    }
}
