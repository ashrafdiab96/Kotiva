<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ScienceRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * The science section reproduces client-supplied clinical copy on a
 * doctor-positioned brand in a regulated category. The renderer's contract is
 * that it changes PRESENTATION ONLY — so these tests assert the no-loss
 * guarantee itself, not merely that the method returns a string.
 *
 * No database: the real product text is read straight from the legacy bundle,
 * which is the exact input production seeds from.
 */
final class ScienceRendererTest extends TestCase
{
    private ScienceRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ScienceRenderer;
    }

    #[Test]
    public function empty_or_missing_science_renders_nothing(): void
    {
        $this->assertSame('', $this->renderer->render(null));
        $this->assertSame('', $this->renderer->render(''));
        $this->assertSame('', $this->renderer->render("   \n\t  "));
    }

    #[Test]
    public function every_real_product_renders_without_losing_content(): void
    {
        $products = $this->legacyProducts();

        $this->assertCount(25, $products, 'the legacy bundle should hold 25 SKUs');

        $rendered = 0;

        foreach ($products as $product) {
            $science = (string) ($product['science'] ?? '');

            if (trim($science) === '') {
                continue;
            }

            // render() throws if any sentence or citation fails to survive.
            $html = $this->renderer->render($science, 'product '.$product['id']);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('sci-block', $html);
            $rendered++;
        }

        $this->assertSame(25, $rendered, 'every product carries science text');
    }

    #[Test]
    public function every_citation_survives_into_the_output(): void
    {
        foreach ($this->legacyProducts() as $product) {
            $science = (string) ($product['science'] ?? '');
            $html = $this->renderer->render($science, 'product '.$product['id']);

            preg_match_all('/https?:\/\/[^\s]+/i', $science, $matches);

            foreach ($matches[0] as $url) {
                $this->assertStringContainsString(
                    str_replace('&', '&amp;', $url),
                    $html,
                    "citation dropped for product {$product['id']}"
                );
            }
        }
    }

    #[Test]
    public function structural_labels_become_tags_rather_than_prose(): void
    {
        $html = $this->renderer->render(
            "Niacinamide\nMOA: acts on the barrier\nRef:\nhttps://example.test/study"
        );

        $this->assertStringContainsString('<h4 class="sci-name">Niacinamide</h4>', $html);
        $this->assertStringContainsString('<span class="sci-tag">How it works</span>', $html);
        $this->assertStringContainsString('<span class="sci-tag">Evidence</span>', $html);
        $this->assertStringContainsString('href="https://example.test/study"', $html);
        // "MOA:" and "Ref:" are consumed into those tags, never printed raw.
        $this->assertStringNotContainsString('MOA:', $html);
        $this->assertStringNotContainsString('Ref:', $html);
    }

    #[Test]
    public function a_non_breaking_space_does_not_count_as_lost_content(): void
    {
        // Real client text is peppered with U+00A0. PHP's trim() leaves it where
        // JavaScript's strips it, which once made 10 of 25 pages diverge; and a
        // /u-less split made the guard report loss that had not happened.
        $html = $this->renderer->render("Vitamin E\nMOA: has antioxidant properties\u{00A0}");

        $this->assertStringContainsString('has antioxidant properties</p>', $html);
    }

    /*
     | The two tests below drive assertNoLoss() directly. They exist because an
     | assertion that never fires is indistinguishable from one that has been
     | accidentally disabled, and this particular assertion is the reason the
     | science section can ship without a fresh claims review.
     */

    #[Test]
    public function the_guard_rejects_output_that_dropped_a_citation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dropped a citation');

        $this->assertNoLoss(
            "Marine Collagen\nMOA: stimulates repair\nhttps://example.test/study",
            '<div class="sci-block"><h4 class="sci-name">Marine Collagen</h4>'
                .'<p class="sci-line"><span class="sci-tag">How it works</span>stimulates repair</p></div>',
        );
    }

    #[Test]
    public function the_guard_rejects_output_that_dropped_a_sentence(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/dropped \d+ token/');

        $this->assertNoLoss(
            "Niacinamide\nReduces transepidermal water loss substantially",
            '<div class="sci-block"><h4 class="sci-name">Niacinamide</h4></div>',
        );
    }

    #[Test]
    public function the_guard_passes_when_nothing_was_lost(): void
    {
        $science = "Niacinamide\nMOA: supports the barrier\nhttps://example.test/a";

        // Rendering its own output back through the guard must not throw.
        $this->assertNoLoss($science, $this->renderer->render($science));

        $this->addToAssertionCount(1);
    }

    /**
     * assertNoLoss is private by design — it is an internal invariant, not API.
     * Reflection is the honest way to test it without widening its visibility
     * purely for the test suite.
     */
    private function assertNoLoss(string $raw, string $html): void
    {
        $method = new ReflectionMethod(ScienceRenderer::class, 'assertNoLoss');
        $method->invoke($this->renderer, $raw, $html, 'test product');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyProducts(): array
    {
        $path = dirname(__DIR__, 2).'/legacy/js/data-full.js';
        $source = (string) file_get_contents($path);

        preg_match('/const\s+KOTIVA_PRODUCTS_FULL\s*=\s*(\[[\s\S]*?\]);/', $source, $m);

        /** @var array<int, array<string, mixed>> $decoded */
        $decoded = json_decode($m[1], true);

        return $decoded;
    }
}
