<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Admin;
use App\Models\Order;
use App\Support\Export\CsvExports;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * No create action, deliberately.
     *
     * An order is created by checkout, inside the transaction that reserves and
     * fulfils its stock. A blank order typed here would have no reservation
     * behind it, so its items would be promised twice — once to it and once to
     * the next shopper.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                // A bulk file of customer names, emails and phone numbers is a
                // different thing from viewing one order, so it follows the
                // customer-data capability: staff work orders but do not take
                // the customer list home.
                ->visible(fn (): bool => ($user = auth()->user()) instanceof Admin && $user->canViewCustomers())
                ->action(function (): StreamedResponse {
                    /** @var Builder<Order> $query */
                    $query = $this->getFilteredTableQuery();

                    return CsvExports::orders($query);
                }),
        ];
    }
}
