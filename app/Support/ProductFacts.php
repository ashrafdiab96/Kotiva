<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * The icon-led "at a glance" facts block on a product page.
 *
 * Every fact is drawn from data KOTIVA already supplied. Two facts a shopper
 * might expect are DELIBERATELY ABSENT and must stay absent:
 *
 *   • free-from declarations — `free_from` is empty on all 25 products, and
 *     populating it from the client's documents is a claims-class change, not
 *     a silent fill.
 *   • shelf life / period-after-opening — does not exist in any KOTIVA source.
 *
 * A fact with no data is omitted entirely. Nothing is inferred and no
 * placeholder is ever rendered, because a blank fact still reads as a fact.
 */
final class ProductFacts
{
    private const ZONE_LABELS = [
        'face' => 'Face',
        'body' => 'Body',
        'hair' => 'Hair & scalp',
        'lips' => 'Lips',
        'intimate' => 'Intimate area',
    ];

    /**
     * Two facts (format, zone) live only in the authored routine model, which
     * is keyed by the legacy numeric product id. Matching on NAME rather than
     * id keeps the association correct if ids ever shift — a reseed, an import
     * or a soft-deleted row would otherwise silently attach one product's
     * format to another's page.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    public function for(Product $product): array
    {
        $model = $this->routineModelByName()[$product->name] ?? [];

        $zones = array_values(array_filter(array_map(
            fn (string $z): ?string => self::ZONE_LABELS[$z] ?? null,
            (array) ($model['zone'] ?? [])
        )));

        $candidates = [
            ['key' => 'format', 'label' => 'Format', 'value' => (string) ($model['format'] ?? '')],
            ['key' => 'zone', 'label' => 'Use on', 'value' => implode(' & ', $zones)],
            ['key' => 'skin', 'label' => 'Skin type', 'value' => (string) $product->skin_type],
            ['key' => 'target', 'label' => 'Targets', 'value' => (string) $product->concern],
            ['key' => 'size', 'label' => 'Size', 'value' => (string) $product->volume],
        ];

        return array_values(array_filter(
            $candidates,
            fn (array $f): bool => trim($f['value']) !== ''
        ));
    }

    /**
     * The inline SVG for a fact, matching the set the static pages rendered.
     */
    public function icon(string $key): string
    {
        $paths = [
            'format' => '<path d="M8 2h8M9 2v4.2a6 6 0 0 0-.9 3.1V19a3 3 0 0 0 3 3h1.8a3 3 0 0 0 3-3V9.3a6 6 0 0 0-.9-3.1V2"/>',
            'zone' => '<circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
            'skin' => '<path d="M12 3c4 4 6 6.7 6 9.6A6 6 0 0 1 6 12.6C6 9.7 8 7 12 3z"/>',
            'target' => '<circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="3.4"/>',
            'size' => '<path d="M4 7h16M4 17h16M7 7v10M17 7v10"/>',
        ];

        return '<svg class="pdp-fact-icon" width="22" height="22" viewBox="0 0 24 24" fill="none"'
            .' stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'
            .' aria-hidden="true">'.($paths[$key] ?? '').'</svg>';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function routineModelByName(): array
    {
        return Cache::rememberForever('kotiva.routine_model_by_name', function (): array {
            $path = base_path('resources/routine/routine-model.json');

            if (! is_file($path)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (! is_array($decoded) || ! isset($decoded['products']) || ! is_array($decoded['products'])) {
                return [];
            }

            $byName = [];
            foreach ($decoded['products'] as $entry) {
                if (is_array($entry) && isset($entry['name']) && is_string($entry['name'])) {
                    $byName[$entry['name']] = $entry;
                }
            }

            return $byName;
        });
    }
}
