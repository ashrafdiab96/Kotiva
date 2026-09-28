<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * A spreadsheet's header row and data rows, as plain strings.
 *
 * Rows are keyed by their 1-based line number in the file (the header is line
 * 1), so an error can name the line the admin will actually see when they
 * open the file — not an index that is off by one from it.
 */
final readonly class SpreadsheetData
{
    /**
     * @param  list<string>  $headers
     * @param  array<int, list<string>>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * @return array<int, list<string>>
     */
    public function preview(int $limit = 10): array
    {
        return array_slice($this->rows, 0, $limit, true);
    }
}
