<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\OrderNumber;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Order numbers are read aloud to couriers and typed back by customers, so the
 * properties tested here are about legibility and collision safety rather than
 * formatting for its own sake.
 *
 * No database: the existence check is injected, which is also how the retry
 * behaviour can be driven deterministically.
 */
final class OrderNumberTest extends TestCase
{
    #[Test]
    public function it_matches_the_documented_shape(): void
    {
        $number = OrderNumber::generate(fn (): bool => false);

        $this->assertMatchesRegularExpression('/^KOT-[A-Z2-9]{6}$/', $number);
        $this->assertTrue(OrderNumber::isValid($number));
    }

    #[Test]
    public function it_never_uses_characters_that_can_be_misheard(): void
    {
        // 0/O and 1/I are excluded on purpose: these numbers are read out on
        // the phone for cash-on-delivery confirmations.
        $forbidden = ['0', 'O', '1', 'I'];

        for ($i = 0; $i < 400; $i++) {
            $body = substr(OrderNumber::generate(fn (): bool => false), 4);

            foreach ($forbidden as $char) {
                $this->assertStringNotContainsString($char, $body, "generated {$body}");
            }
        }
    }

    #[Test]
    public function it_retries_when_a_number_is_already_taken(): void
    {
        $seen = [];

        // Refuse the first two candidates, accept the third.
        $number = OrderNumber::generate(function (string $candidate) use (&$seen): bool {
            $seen[] = $candidate;

            return count($seen) < 3;
        });

        $this->assertCount(3, $seen);
        $this->assertSame($seen[2], $number);
    }

    #[Test]
    public function it_gives_up_loudly_rather_than_looping_forever(): void
    {
        $attempts = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not generate a unique order number');

        try {
            OrderNumber::generate(function () use (&$attempts): bool {
                $attempts++;

                return true; // everything is taken
            });
        } finally {
            $this->assertSame(
                (int) config('kotiva.order_number.max_attempts'),
                $attempts,
                'it must stop at the configured attempt limit'
            );
        }
    }

    #[Test]
    public function generated_numbers_do_not_repeat_in_practice(): void
    {
        $numbers = [];

        for ($i = 0; $i < 500; $i++) {
            $numbers[] = OrderNumber::generate(fn (): bool => false);
        }

        $this->assertCount(500, array_unique($numbers), 'collisions at this volume indicate weak randomness');
    }

    #[Test]
    public function it_rejects_malformed_values(): void
    {
        $this->assertFalse(OrderNumber::isValid('KOT-ABC'));          // too short
        $this->assertFalse(OrderNumber::isValid('KOT-ABCDEFG'));      // too long
        $this->assertFalse(OrderNumber::isValid('ABC-DEFGHJ'));       // wrong prefix
        $this->assertFalse(OrderNumber::isValid('KOT-ABCDE0'));       // excluded char
        $this->assertFalse(OrderNumber::isValid('KOT-abcdef'));       // lowercase
        $this->assertFalse(OrderNumber::isValid(''));
    }
}
