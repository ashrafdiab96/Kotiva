<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * The result of validating every row, before anything is written.
 *
 * Kept separate from the write step so the all-or-nothing rule is a decision
 * the caller makes on a finished report, not something discovered half-way
 * through an import that has already changed the catalog.
 */
final readonly class ImportReport
{
    /**
     * @param  array<int, array<string, mixed>>  $valid  line => parsed row
     * @param  array<int, list<string>>  $errors  line => messages
     */
    public function __construct(
        public array $valid,
        public array $errors,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function total(): int
    {
        return count($this->valid) + count($this->errors);
    }

    /**
     * A flat, readable list, first N lines only — enough to fix the file,
     * without a notification the size of the spreadsheet.
     *
     * @return list<string>
     */
    public function errorLines(int $limit = 20): array
    {
        $lines = [];

        foreach ($this->errors as $line => $messages) {
            $lines[] = 'Line '.$line.': '.implode(' ', $messages);

            if (count($lines) >= $limit) {
                break;
            }
        }

        return $lines;
    }
}
