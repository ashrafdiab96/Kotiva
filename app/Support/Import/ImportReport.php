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
     * @param  list<string>  $warnings  accepted rows worth a second look
     */
    public function __construct(
        public array $valid,
        public array $errors,
        public array $warnings = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Rows that will import, but that a person should see first — a price
     * recalculated because the sheet carried only one of the two columns, or
     * two supplied prices that disagree at the configured VAT rate.
     *
     * Deliberately not errors. Both cases are legitimate, and refusing the file
     * over either would make an ordinary repricing sheet unusable; but both
     * change or question a money figure, and those do not belong in a silent
     * success message.
     */
    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * @return list<string>
     */
    public function warningLines(int $limit = 20): array
    {
        return array_slice($this->warnings, 0, $limit);
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
