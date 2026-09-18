<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ProductResource;
use App\Jobs\ImportProducts as ImportProductsJob;
use App\Models\Admin;
use App\Support\Import\ImportReport;
use App\Support\Import\ProductImporter;
use App\Support\Import\SpreadsheetData;
use App\Support\Import\SpreadsheetReader;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Product import (§7.2): upload, preview with column mapping, validate, import.
 *
 * A page rather than a modal action, reached from the "Import" action on the
 * products list. The brief's flow — preview ten parsed rows, let the admin
 * remap columns, show row-level errors, then import — needs state that
 * survives several round trips, and a modal that closes on the first
 * validation error is the wrong container for that.
 *
 * The uploaded file is copied to private storage immediately, because
 * Livewire's temporary upload can be garbage-collected before a queued job
 * reaches it.
 */
final class ImportProducts extends Page
{
    /** Imports above this many rows run as a queued job (§7.2). */
    public const QUEUE_THRESHOLD = 100;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $title = 'Import products';

    protected static string $view = 'filament.pages.import-products';

    /** Reached from the products list, not the sidebar. */
    protected static bool $shouldRegisterNavigation = false;

    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    public ?string $storedPath = null;

    public ?string $extension = null;

    public ?string $originalName = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var array<int, list<string>> */
    public array $previewRows = [];

    public int $totalRows = 0;

    /** @var array<string, int|string|null> field => column index */
    public array $mapping = [];

    public bool $skipInvalid = false;

    /** @var list<string> */
    public array $errorLines = [];

    public int $errorCount = 0;

    public ?int $validCount = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof Admin && $user->canManageCatalog();
    }

    public function updatedUpload(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
        ]);

        $this->reset(['headers', 'previewRows', 'totalRows', 'mapping', 'errorLines', 'errorCount', 'validCount']);
        $this->discardStoredFile();

        $file = $this->upload;

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $extension = $extension === 'txt' ? 'csv' : $extension;

        if (! in_array($extension, SpreadsheetReader::SUPPORTED, true)) {
            $this->addError('upload', 'Upload a .csv or .xlsx file.');

            return;
        }

        $this->extension = $extension;
        $this->originalName = $file->getClientOriginalName();
        $this->storedPath = $file->storeAs('imports', Str::uuid()->toString().'.'.$extension, 'local') ?: null;

        try {
            $data = $this->data();
        } catch (Throwable $e) {
            $this->addError('upload', 'That file could not be read: '.$e->getMessage());
            $this->discardStoredFile();

            return;
        }

        if ($data->headers === [] || $data->rowCount() === 0) {
            $this->addError('upload', 'The file has no data rows under its header row.');
            $this->discardStoredFile();

            return;
        }

        $this->headers = $data->headers;
        $this->previewRows = $data->preview(10);
        $this->totalRows = $data->rowCount();
        $this->mapping = app(ProductImporter::class)->autoMap($data->headers);
    }

    /**
     * Any change of mapping invalidates the last validation report.
     */
    public function updatedMapping(): void
    {
        $this->reset(['errorLines', 'errorCount', 'validCount']);
    }

    /**
     * Pass 1 only: report what would happen, write nothing.
     */
    public function check(): void
    {
        $report = $this->report();

        if ($report instanceof ImportReport) {
            $this->validCount = count($report->valid);
            $this->errorCount = count($report->errors);
            $this->errorLines = $report->errorLines();
        }
    }

    public function runImport(): void
    {
        $report = $this->report();

        if (! $report instanceof ImportReport) {
            return;
        }

        $this->validCount = count($report->valid);
        $this->errorCount = count($report->errors);
        $this->errorLines = $report->errorLines();

        // All or nothing unless the admin has explicitly chosen otherwise.
        if ($report->hasErrors() && ! $this->skipInvalid) {
            Notification::make()
                ->danger()
                ->title('Nothing imported')
                ->body($this->errorCount.' row(s) have errors. Fix them, or tick "Skip invalid rows" to import the rest.')
                ->send();

            return;
        }

        if (count($report->valid) === 0) {
            Notification::make()->warning()->title('No valid rows to import')->send();

            return;
        }

        $adminId = (int) Filament::auth()->id();

        if ($this->totalRows > self::QUEUE_THRESHOLD) {
            ImportProductsJob::dispatch(
                (string) $this->storedPath,
                (string) $this->extension,
                (string) $this->originalName,
                $this->normalisedMapping(),
                $this->skipInvalid,
                $adminId,
            );

            Notification::make()
                ->info()
                ->title('Import started')
                ->body($this->totalRows.' rows are importing in the background. The bell will tell you when it finishes.')
                ->send();

            // The job owns the stored file now.
            $this->storedPath = null;
            $this->resetUpload();

            return;
        }

        try {
            $result = app(ProductImporter::class)->import($report, $adminId, (string) $this->originalName);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Import failed; nothing was changed')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Import finished')->body($result->describe())->send();

        $this->discardStoredFile();
        $this->resetUpload();
    }

    public function startOver(): void
    {
        $this->discardStoredFile();
        $this->resetUpload();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public function getFields(): array
    {
        return ProductImporter::FIELDS;
    }

    /**
     * The preview, re-read through the current mapping, so the admin sees
     * exactly which value lands in which field before anything is written.
     *
     * @return array<int, array<string, string>>
     */
    public function getMappedPreview(): array
    {
        $mapping = $this->normalisedMapping();
        $mapped = [];

        foreach ($this->previewRows as $line => $cells) {
            foreach ($mapping as $field => $index) {
                if ($index !== null) {
                    $mapped[$line][$field] = Str::limit($cells[$index] ?? '', 40);
                }
            }
        }

        return $mapped;
    }

    /**
     * @return list<string>
     */
    public function getMissingRequired(): array
    {
        return app(ProductImporter::class)->missingRequired($this->normalisedMapping());
    }

    public function getProductsUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    private function report(): ?ImportReport
    {
        if ($this->storedPath === null) {
            Notification::make()->warning()->title('Upload a file first')->send();

            return null;
        }

        $missing = $this->getMissingRequired();

        if ($missing !== []) {
            Notification::make()->danger()->title('Map the required columns')->body('Still unmapped: '.implode(', ', $missing).'.')->send();

            return null;
        }

        return app(ProductImporter::class)->validate($this->data(), $this->normalisedMapping());
    }

    private function data(): SpreadsheetData
    {
        return app(SpreadsheetReader::class)->read(
            Storage::disk('local')->path((string) $this->storedPath),
            (string) $this->extension,
        );
    }

    /**
     * Select values arrive from the browser as strings; "" means unmapped.
     *
     * @return array<string, int|null>
     */
    private function normalisedMapping(): array
    {
        $mapping = [];

        foreach (array_keys(ProductImporter::FIELDS) as $field) {
            $value = $this->mapping[$field] ?? null;
            $mapping[$field] = ($value === null || $value === '') ? null : (int) $value;
        }

        return $mapping;
    }

    private function discardStoredFile(): void
    {
        if ($this->storedPath !== null) {
            Storage::disk('local')->delete($this->storedPath);
            $this->storedPath = null;
        }
    }

    private function resetUpload(): void
    {
        $this->reset(['upload', 'extension', 'originalName', 'headers', 'previewRows', 'totalRows', 'mapping', 'skipInvalid', 'errorLines', 'errorCount', 'validCount']);
    }
}
