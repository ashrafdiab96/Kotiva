<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * What an import actually changed.
 */
final readonly class ImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $stockAdjusted = 0,
        public int $skipped = 0,
    ) {}

    public function describe(): string
    {
        $parts = [];

        foreach ([
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
        ] as $label => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.$label;
            }
        }

        if ($this->stockAdjusted > 0) {
            $parts[] = $this->stockAdjusted.' stock level'.($this->stockAdjusted === 1 ? '' : 's').' changed';
        }

        if ($this->skipped > 0) {
            $parts[] = $this->skipped.' invalid row'.($this->skipped === 1 ? '' : 's').' skipped';
        }

        return $parts === [] ? 'Nothing to import.' : ucfirst(implode(', ', $parts)).'.';
    }
}
