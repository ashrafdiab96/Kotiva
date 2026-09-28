<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Support\Facades\DB;

/**
 * Bulk city import from CSV (§7.3).
 *
 * A plain class rather than a Filament Importer on purpose. Filament's import
 * action dispatches a queued batch, which on this project means an upload that
 * appears to succeed and then does nothing at all until a worker runs — dev
 * uses the database queue driver. A city list is a handful of short rows; doing
 * it inline and reporting the result immediately is both simpler and honest
 * about when the work happened.
 *
 * Upserts on (zone_id, name_en), which is the table's own unique key, so
 * re-importing the same file is idempotent exactly as re-running ShippingSeeder
 * is. Blindly inserting would throw an integrity error on the second run.
 */
final class CityCsvImporter
{
    /**
     * Column headers this recognises, so a file exported from a spreadsheet
     * works without the admin having to strip its header row.
     */
    private const HEADER_HINTS = ['name_en', 'name', 'city', 'city_en', 'english'];

    public function import(string $contents, ShippingZone $zone): CityImportSummary
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $rows = $this->rows($contents);

        DB::transaction(function () use ($rows, $zone, &$created, &$updated, &$skipped, &$errors): void {
            foreach ($rows as $lineNumber => $row) {
                $nameEn = trim((string) ($row[0] ?? ''));
                $nameAr = isset($row[1]) ? trim((string) $row[1]) : null;

                if ($nameEn === '') {
                    $skipped++;
                    $errors[] = 'Line '.$lineNumber.': no city name.';

                    continue;
                }

                $existing = ShippingCity::query()
                    ->where('zone_id', $zone->getKey())
                    ->where('name_en', $nameEn)
                    ->first();

                if ($existing instanceof ShippingCity) {
                    // Only fill a blank Arabic name; an import must not wipe
                    // one an admin typed by hand because the CSV omitted it.
                    if ($nameAr !== null && $nameAr !== '' && ($existing->name_ar === null || $existing->name_ar === '')) {
                        $existing->update(['name_ar' => $nameAr]);
                        $updated++;

                        continue;
                    }

                    $skipped++;

                    continue;
                }

                ShippingCity::create([
                    'zone_id' => $zone->getKey(),
                    'name_en' => $nameEn,
                    'name_ar' => ($nameAr === '' ? null : $nameAr),
                    'is_active' => true,
                ]);

                $created++;
            }
        });

        return new CityImportSummary(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * Parsed data rows, keyed by their 1-based line number in the file.
     *
     * @return array<int, list<string>>
     */
    private function rows(string $contents): array
    {
        // A file saved from Excel carries a UTF-8 BOM, which would otherwise
        // become part of the first city's name and create a duplicate.
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;

        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $rows = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;

            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);

            // Skip a header row rather than importing a city called "name_en".
            if ($lineNumber === 1 && $this->looksLikeHeader($cells)) {
                continue;
            }

            $rows[$lineNumber] = array_map(
                fn ($cell): string => (string) $cell,
                $cells
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    private function looksLikeHeader(array $cells): bool
    {
        $first = strtolower(trim((string) ($cells[0] ?? '')));

        return in_array($first, self::HEADER_HINTS, true);
    }
}
