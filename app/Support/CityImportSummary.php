<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a city CSV import did.
 *
 * A value object rather than an array so the caller cannot read a key that was
 * never set, and so "nothing happened" is distinguishable from "everything was
 * already there" — which look identical if you only count created rows.
 */
final readonly class CityImportSummary
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * A single sentence for the dashboard notification.
     */
    public function describe(): string
    {
        if ($this->total() === 0) {
            return 'The file contained no city rows.';
        }

        $parts = [];

        if ($this->created > 0) {
            $parts[] = $this->created.' added';
        }

        if ($this->updated > 0) {
            $parts[] = $this->updated.' updated';
        }

        if ($this->skipped > 0) {
            $parts[] = $this->skipped.' skipped';
        }

        return ucfirst(implode(', ', $parts)).'.';
    }
}
