<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralPartnerResource\Pages;

use App\Filament\Resources\ReferralPartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditReferralPartner extends EditRecord
{
    protected static string $resource = ReferralPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            // Soft delete (super-admin only, via GatedByRole). No force delete:
            // it would cascade the partner's visit history away.
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
