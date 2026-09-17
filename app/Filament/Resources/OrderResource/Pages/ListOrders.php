<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * No create action, deliberately.
     *
     * An order is created by checkout, inside the transaction that reserves and
     * fulfils its stock. A blank order typed here would have no reservation
     * behind it, so its items would be promised twice — once to it and once to
     * the next shopper. The export action is added with §7.2's export work.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
