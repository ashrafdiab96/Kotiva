<?php

declare(strict_types=1);

namespace App\Support\Import;

use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads the first sheet of a .csv or .xlsx file into strings.
 *
 * The reader is chosen from the extension the caller already knows, never
 * guessed: openspout's ReaderFactory is deprecated precisely because guessing
 * is brittle, and an upload's temporary path is not a name worth trusting.
 */
final class SpreadsheetReader
{
    public const SUPPORTED = ['csv', 'xlsx'];

    public function read(string $path, string $extension): SpreadsheetData
    {
        $extension = strtolower($extension);

        $reader = match ($extension) {
            // A .txt upload is what a browser sometimes reports for a CSV.
            'csv', 'txt' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw new InvalidArgumentException('Unsupported file type: .'.$extension.'. Upload a .csv or .xlsx file.'),
        };

        $reader->open($path);

        $headers = [];
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(fn (mixed $v): string => $this->stringify($v), $row->toArray());

                    if ($line === 1) {
                        $headers = array_map(fn (string $h): string => $this->cleanHeader($h), $cells);

                        continue;
                    }

                    // Blank rows (common at the bottom of an edited sheet) are
                    // not rows; counting them would report phantom errors.
                    if (trim(implode('', $cells)) === '') {
                        continue;
                    }

                    $rows[$line] = $cells;
                }

                // First sheet only: a workbook's other tabs are notes, not data.
                break;
            }
        } finally {
            $reader->close();
        }

        return new SpreadsheetData($headers, $rows);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value)) {
            // Excel stores 50 as 50.0; "50.0" is not a valid stock quantity.
            return floor($value) === $value && abs($value) < PHP_INT_MAX
                ? (string) (int) $value
                : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        if (is_scalar($value)) {
            // Not trimmed here: long text (science above all) must survive a
            // round trip byte for byte. ProductImporter trims per field type.
            return (string) $value;
        }

        return '';
    }

    private function cleanHeader(string $header): string
    {
        // Excel's UTF-8 BOM would otherwise make the first header "\u{FEFF}sku",
        // which then fails to auto-map to the sku field.
        return trim((string) preg_replace('/^\x{FEFF}/u', '', $header));
    }
}
