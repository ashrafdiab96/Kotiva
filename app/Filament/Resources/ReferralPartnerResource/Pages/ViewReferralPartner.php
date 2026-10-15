<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Pages;

use App\Filament\Resources\ReferralPartnerResource;
use App\Filament\Resources\ReferralPartnerResource\Widgets\PartnerRecentVisits;
use App\Filament\Resources\ReferralPartnerResource\Widgets\PartnerVisitsChart;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

final class ViewReferralPartner extends ViewRecord
{
    protected static string $resource = ReferralPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PartnerVisitsChart::class,
            PartnerRecentVisits::class,
        ];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }
}
