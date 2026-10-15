<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Pages;

use App\Filament\Resources\ReferralPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

final class ListReferralPartners extends ListRecords
{
    protected static string $resource = ReferralPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
