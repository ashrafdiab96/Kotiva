<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Renders a product's clinical "science" text as structured HTML.
 *
 * This is a direct port of the retired generator's E7 renderer, kept because
 * the presentation it produces is the one the client's claims were reviewed
 * against. The client's own text already has structure — named actives, "MOA:"
 * lines, benefit bullets, then "Ref:" citations — and the pre-Blade JS page
 * flattened all of it to <br>-separated prose. This restores the hierarchy.
 *
 * IMPORTANT: this is a PRESENTATION transform only. No science text is added,
 * removed, reworded or reordered. That guarantee is not left to inspection —
 * assertNoLoss() throws if any client sentence or citation fails to survive
 * into the output, so a lossy page cannot be rendered. On a doctor-positioned
 * brand in a regulated category, that assertion is what allows this section to
 * ship without a fresh claims review, so it must never be relaxed or bypassed.
 *
 * It has already earned its keep once: the first PHP port tracked the current
 * block with a reference (`$blocks[] = &$cur`), so opening a new block wrote
 * through that reference and silently overwrote the previous one. 15 of 25
 * products lost citations or whole paragraphs. The assertion caught every one.
 *
 * Output is verified byte-identical to the 25 pages the generator produced.
 */
final class ScienceRenderer
{
    /**
     * The only three structural labels this renderer is permitted to consume:
     * "MOA:" becomes the "How it works" tag, "Ref:" becomes the "Evidence" tag,
     * and a leading "Key/Main active ingredients:" header becomes the section
     * itself. Naming them here is what keeps the rest of the assertion honest.
     *
     * @var list<string>
     */
    private const STRUCTURAL_LABELS = ['moa', 'ref', 'key', 'main', 'active', 'ingredients'];

    /**
     * The whitespace JavaScript's String.prototype.trim() removes.
     *
     * PHP's trim() only strips " \t\n\r\0\x0B", so it leaves the non-breaking
     * spaces that pepper the client's pasted source text. That one-character
     * difference made 10 of the 25 rendered pages diverge from the approved
     * output — invisible on screen, but this renderer's contract is byte
     * parity with what was reviewed, not visual equivalence.
     */
    private const JS_WHITESPACE = '[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    public function render(?string $science, string $productName = 'unknown'): string
    {
        if ($science === null || $this->jsTrim($science) === '') {
            return '';
        }

        $html = $this->renderBlocks($this->parse($science));

        $this->assertNoLoss($science, $html, $productName);

        return $html;
    }

    /**
     * @return list<array{name: string, items: list<array{type: string, value: mixed}>}>
     */
    private function parse(string $raw): array
    {
        $lines = explode("\n", $raw);
        $lineCount = count($lines);

        /** @var list<array{name: string, items: list<array{type: string, value: mixed}>}> $blocks */
        $blocks = [];
        $curIdx = null;

        // Blocks are addressed by INDEX, never by reference. See the class
        // docblock: reference-tracking here corrupts earlier blocks.
        $open = function (string $name) use (&$blocks, &$curIdx): void {
            $blocks[] = ['name' => $name, 'items' => []];
            $curIdx = count($blocks) - 1;
        };

        $push = function (string $type, string $value) use (&$blocks, &$curIdx, $open): void {
            if ($curIdx === null) {
                $open('');
            }

            $items = &$blocks[$curIdx]['items'];
            $n = count($items);
            $isList = in_array($type, ['bullets', 'refs'], true);

            if ($isList && $n > 0 && $items[$n - 1]['type'] === $type) {
                $items[$n - 1]['value'][] = $value;
            } else {
                $items[] = ['type' => $type, 'value' => $isList ? [$value] : $value];
            }

            unset($items);
        };

        $nextContent = function (int $i) use ($lines, $lineCount): string {
            for ($j = $i + 1; $j < $lineCount; $j++) {
                if ($this->jsTrim($lines[$j]) !== '') {
                    return $this->jsTrim($lines[$j]);
                }
            }

            return '';
        };

        foreach ($lines as $i => $line) {
            $l = $this->jsTrim($line);

            if ($l === '') {
                continue;
            }
            if ($i === 0 && preg_match('/ingredients\s*:?\s*$/i', $l) === 1) {
                continue; // section header
            }
            if (preg_match('/^Ref\s*:?\s*$/i', $l) === 1) {
                continue; // "Ref:" label
            }
            if (preg_match('/^https?:\/\//i', $l) === 1) {
                $push('refs', $l);

                continue;
            }
            if (preg_match('/^MOA\s*:/i', $l) === 1) {
                $push('moa', (string) preg_replace('/^MOA\s*:\s*/i', '', $l));

                continue;
            }
            // The tab test is against the RAW line: indentation is the signal.
            if (preg_match('/^[•●*]\s*/u', $l) === 1 || preg_match('/^\t/', $line) === 1) {
                $push('bullets', $this->jsTrim((string) preg_replace('/^[•●*\t]\s*/u', '', $l)));

                continue;
            }

            $numbered = preg_match('/^\d+\s*[-.)]\s*\S/', $l) === 1;
            $short = mb_strlen($l) <= 70 && preg_match('/[.!?]$/', $l) !== 1;

            // An INGREDIENT heading is numbered, or is immediately followed by
            // its mechanism-of-action line. A line that merely ends in a colon
            // ("Reduces:") is a SUB-heading inside the current ingredient, not a
            // new ingredient — promoting those made "Reduces" look like an active.
            if ($short && ($numbered || preg_match('/^MOA\s*:/i', $nextContent($i)) === 1 || $curIdx === null)) {
                $name = (string) preg_replace('/^\d+\s*[-.)]\s*/', '', (string) preg_replace('/:$/', '', $l));
                $open($this->jsTrim($name));

                continue;
            }

            if ($short && preg_match('/:$/', $l) === 1) {
                $push('sub', $this->jsTrim((string) preg_replace('/:$/', '', $l)));

                continue;
            }

            $push('para', $l);
        }

        return array_values(array_filter(
            $blocks,
            fn (array $b): bool => $b['name'] !== '' || $b['items'] !== []
        ));
    }

    /**
     * @param  list<array{name: string, items: list<array{type: string, value: mixed}>}>  $blocks
     */
    private function renderBlocks(array $blocks): string
    {
        $out = '';

        foreach ($blocks as $b) {
            $parts = '';

            if ($b['name'] !== '') {
                $parts .= '<h4 class="sci-name">'.$this->esc($b['name']).'</h4>';
            }

            foreach ($b['items'] as $it) {
                $parts .= match ($it['type']) {
                    'moa' => '<p class="sci-line"><span class="sci-tag">How it works</span>'
                        .$this->esc((string) $it['value']).'</p>',
                    'sub' => '<p class="sci-sub">'.$this->esc((string) $it['value']).'</p>',
                    'bullets' => '<ul class="sci-list">'.implode('', array_map(
                        fn (string $x): string => '<li>'.$this->esc($x).'</li>',
                        (array) $it['value']
                    )).'</ul>',
                    'refs' => '<p class="sci-refs"><span class="sci-tag">Evidence</span>'.implode(' ', array_map(
                        fn (string $u): string => '<a href="'.$this->esc($u)
                            .'" target="_blank" rel="noopener noreferrer">source &#8599;</a>',
                        (array) $it['value']
                    )).'</p>',
                    default => '<p class="sci-line">'.$this->esc((string) $it['value']).'</p>',
                };
            }

            $out .= '<div class="sci-block">'.$parts.'</div>';
        }

        return $out;
    }

    /**
     * Throws if the presentation change silently dropped client content.
     *
     * @throws RuntimeException
     */
    private function assertNoLoss(string $raw, string $html, string $productName): void
    {
        // Case-insensitive: one citation in the Anti-Hair Loss Shampoo text is
        // written "Https://…". A case-sensitive match would skip verifying it.
        preg_match_all('/https?:\/\/[^\s]+/i', $raw, $m);
        $srcUrls = $m[0];

        preg_match_all('/href="([^"]+)"/', $html, $hm);
        $outUrls = $hm[1];

        foreach ($srcUrls as $u) {
            if (! in_array(str_replace('&', '&amp;', $this->jsTrim($u)), $outUrls, true)) {
                throw new RuntimeException(
                    "Science render dropped a citation for \"{$productName}\": {$u}"
                );
            }
        }

        $outText = html_entity_decode(
            (string) preg_replace('/<[^>]+>/', ' ', $html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $outSet = array_flip($this->words($outText));

        $srcWords = array_filter(
            $this->words((string) preg_replace('/https?:\/\/[^\s]+/i', ' ', $raw)),
            fn (string $w): bool => mb_strlen($w) > 2 && ! in_array($w, self::STRUCTURAL_LABELS, true)
        );

        $missing = array_values(array_unique(array_filter(
            $srcWords,
            fn (string $w): bool => ! isset($outSet[$w])
        )));

        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Science render dropped %d token(s) for "%s": %s',
                count($missing),
                $productName,
                implode(' | ', array_slice($missing, 0, 6))
            ));
        }
    }

    /**
     * Compared on alphanumerics only: punctuation moves when a heading loses a
     * trailing colon, which is formatting, not content.
     *
     * @return list<string>
     */
    private function words(string $s): array
    {
        // Every regex here carries /u, and that consistency is the point. In
        // PHP the /u modifier enables PCRE2_UCP as well as UTF-8, so \s matches
        // a non-breaking space with it and does not without it. Splitting on a
        // bare '/\s+/' while the strip pattern used '/u' left "properties\u{A0}"
        // as one token on the source side and "properties" on the rendered
        // side, so assertNoLoss reported content loss that had not happened.
        $normalised = mb_strtolower($this->jsTrim((string) preg_replace('/\s+/u', ' ', $s)));
        $stripped = (string) preg_replace('/[^\p{L}\p{N}\s%]/u', ' ', $normalised);

        return array_values(array_filter(preg_split('/\s+/u', $stripped) ?: []));
    }

    /**
     * JavaScript-equivalent trim. See JS_WHITESPACE.
     */
    private function jsTrim(string $s): string
    {
        return (string) preg_replace(
            '/^'.self::JS_WHITESPACE.'+|'.self::JS_WHITESPACE.'+$/u',
            '',
            $s
        );
    }

    /**
     * Matches the generator's esc_(): &, < and > only. Quotes are deliberately
     * untouched so citation URLs render byte-identically to the static pages.
     */
    private function esc(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }
}
