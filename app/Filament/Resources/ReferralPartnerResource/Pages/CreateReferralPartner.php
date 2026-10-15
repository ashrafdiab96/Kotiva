<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Pages;

use App\Filament\Resources\ReferralPartnerResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateReferralPartner extends CreateRecord
{
    protected static string $resource = ReferralPartnerResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
