<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Widgets;

use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The partner's last 100 visits. Never shows ip_hash or user_agent: they
 * exist for de-duplication, not for reading.
 */
final class PartnerRecentVisits extends TableWidget
{
    public const LIMIT = 100;

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Last 100 visits';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::query($this->record))
            ->defaultSort('visited_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('visited_at')->label('When')->dateTime('j M Y, H:i:s'),
                Tables\Columns\TextColumn::make('landing_path')->label('Landed on'),
                Tables\Columns\TextColumn::make('referer')->label('Came from')->placeholder('—')->limit(60)->tooltip(fn (ReferralVisit $record): ?string => $record->referer),
                Tables\Columns\IconColumn::make('is_unique_day')->label('Unique today')->boolean(),
            ])
            ->emptyStateHeading('No visits yet');
    }

    /**
     * Bounded by id rather than LIMIT inside a subquery, which MySQL refuses.
     *
     * @return Builder<ReferralVisit>
     */
    public static function query(?Model $partner): Builder
    {
        if (! $partner instanceof ReferralPartner) {
            return ReferralVisit::query()->whereRaw('1 = 0');
        }

        $ids = ReferralVisit::query()
            ->where('referral_partner_id', $partner->id)
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->pluck('id');

        return ReferralVisit::query()
            ->select(['id', 'referral_partner_id', 'visited_at', 'landing_path', 'referer', 'is_unique_day'])
            ->whereKey($ids->all());
    }
}
