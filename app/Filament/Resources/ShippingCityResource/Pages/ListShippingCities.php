<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingCityResource\Pages;

use App\Filament\Resources\ShippingCityResource;
use App\Models\ShippingZone;
use App\Support\CityCsvImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListShippingCities extends ListRecords
{
    protected static string $resource = ShippingCityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            $this->templateAction(),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Bulk CSV import (§7.3).
     *
     * Runs inline rather than through Filament's queued import batch: dev uses
     * the database queue driver, so a queued import would report success and
     * then do nothing at all until a worker ran. A city list is a few short
     * rows; the admin should see the result immediately.
     */
    private function importAction(): Actions\Action
    {
        return Actions\Action::make('importCities')
            ->label('Import CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Import cities')
            ->modalDescription('One city per row: English name, then optionally the Arabic name. A header row is detected and skipped. Re-importing the same file changes nothing, so it is safe to run twice.')
            ->modalSubmitActionLabel('Import')
            ->form([
                Forms\Components\Select::make('zone_id')
                    ->label('Add to zone')
                    ->options(fn (): array => ShippingZone::query()
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->native(false)
                    ->helperText('Every city in the file is added to this zone.'),

                Forms\Components\FileUpload::make('file')
                    ->label('CSV file')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->required()
                    // Kept in the temporary upload rather than stored: the file
                    // is read once and has no use afterwards, so persisting it
                    // would only accumulate orphans on disk.
                    ->storeFiles(false),
            ])
            ->action(function (array $data): void {
                $zone = ShippingZone::query()->find($data['zone_id'] ?? null);

                if (! $zone instanceof ShippingZone) {
                    Notification::make()->danger()->title('Zone not found')->send();

                    return;
                }

                $contents = $this->uploadedContents($data['file'] ?? null);

                if ($contents === null) {
                    Notification::make()->danger()->title('Could not read that file')->send();

                    return;
                }

                $summary = app(CityCsvImporter::class)->import($contents, $zone);

                $notification = Notification::make()
                    ->title('Import finished')
                    ->body($summary->describe().' Zone: '.$zone->name.'.');

                // Warning rather than success when rows were rejected: an
                // import that silently drops a city is how a shopper ends up
                // unable to choose their own town.
                $summary->hasErrors()
                    ? $notification->warning()->body(
                        $summary->describe().' '.count($summary->errors).' row(s) skipped: '
                        .implode(' ', array_slice($summary->errors, 0, 3))
                    )->send()
                    : $notification->success()->send();
            });
    }

    /**
     * The downloadable template the brief asks for, so the expected shape is
     * discoverable rather than described only in a modal.
     */
    private function templateAction(): Actions\Action
    {
        return Actions\Action::make('cityTemplate')
            ->label('Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (): StreamedResponse {
                $csv = "name_en,name_ar\nRiyadh,الرياض\nJeddah,جدة\n";

                return response()->streamDownload(
                    fn () => print "\u{FEFF}".$csv,
                    'kotiva-cities-template.csv',
                );
            });
    }

    /**
     * Filament hands back an array of uploads keyed by id when storeFiles is
     * off, so the single file has to be dug out rather than used directly.
     */
    private function uploadedContents(mixed $state): ?string
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! $file instanceof UploadedFile) {
            return null;
        }

        $contents = file_get_contents($file->getRealPath());

        return $contents === false ? null : $contents;
    }
}
