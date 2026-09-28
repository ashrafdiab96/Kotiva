<?php

namespace App\Filament\Resources\ProductStockMovementResource\Pages;

use App\Filament\Resources\ProductStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductStockMovements extends ListRecords
{
    protected static string $resource = ProductStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
