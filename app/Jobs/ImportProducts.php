<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Admin;
use App\Support\Import\ProductImporter;
use App\Support\Import\SpreadsheetReader;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A large product import (§7.2: queued above 100 rows).
 *
 * Re-validates the stored file rather than trusting the page's earlier check:
 * the catalog may have changed while the job waited, and a slug that was free
 * then may not be now. The admin hears the outcome through the dashboard's
 * notification bell, since the page that started it may be long closed.
 */
final class ImportProducts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    /**
     * Not retried: a half-understood failure re-run automatically could apply
     * the same stock change twice.
     */
    public int $tries = 1;

    /**
     * @param  array<string, int|null>  $mapping
     */
    public function __construct(
        public readonly string $path,
        public readonly string $extension,
        public readonly string $originalName,
        public readonly array $mapping,
        public readonly bool $skipInvalid,
        public readonly int $adminId,
    ) {}

    public function handle(SpreadsheetReader $reader, ProductImporter $importer): void
    {
        $admin = Admin::query()->find($this->adminId);

        try {
            $data = $reader->read(Storage::disk('local')->path($this->path), $this->extension);
            $report = $importer->validate($data, $this->mapping);

            if ($report->hasErrors() && ! $this->skipInvalid) {
                $this->notify($admin, Notification::make()
                    ->danger()
                    ->title('Import of '.$this->originalName.' refused')
                    ->body(count($report->errors).' invalid row(s); nothing was imported. '.implode(' ', $report->errorLines(3))));

                return;
            }

            $result = $importer->import($report, $this->adminId, $this->originalName);

            $this->notify($admin, Notification::make()
                ->success()
                ->title('Import of '.$this->originalName.' finished')
                ->body($result->describe()));
        } catch (Throwable $e) {
            $this->notify($admin, Notification::make()
                ->danger()
                ->title('Import of '.$this->originalName.' failed')
                ->body('Nothing was imported. '.$e->getMessage()));

            throw $e;
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }

    private function notify(?Admin $admin, Notification $notification): void
    {
        if ($admin instanceof Admin) {
            $notification->sendToDatabase($admin);
        }
    }
}
