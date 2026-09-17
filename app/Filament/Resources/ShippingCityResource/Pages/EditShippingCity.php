<?php

namespace App\Filament\Resources\ShippingCityResource\Pages;

use App\Filament\Resources\ShippingCityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingCity extends EditRecord
{
    protected static string $resource = ShippingCityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
