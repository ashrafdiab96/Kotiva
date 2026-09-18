<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\ImportProducts;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Support\Export\CsvExports;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('import')
                    ->label('Import from Excel/CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(ImportProducts::getUrl()),

                Actions\Action::make('template')
                    ->label('Download import template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (): StreamedResponse => CsvExports::template()),

                Actions\Action::make('export')
                    ->label('Export products (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    // Exports what the table currently shows, filters
                    // included — the same columns the importer reads, so an
                    // export can be edited and imported straight back.
                    ->action(function (): StreamedResponse {
                        /** @var Builder<Product> $query */
                        $query = $this->getFilteredTableQuery();

                        return CsvExports::products($query);
                    }),
            ])
                ->label('Import / export')
                ->icon('heroicon-m-arrows-up-down')
                ->button()
                ->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}
