<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates the customer-facing order number, e.g. KOT-7F3K2Q.
 *
 * The alphabet excludes 0/O and 1/I. That is not decoration: these numbers get
 * read aloud and written down when a cash-on-delivery courier calls, and a
 * number that can be misheard is a number that reaches the wrong order.
 *
 * Uniqueness is enforced by a unique index on orders.order_no; this only tries
 * to avoid the collision so the insert does not have to fail. Collisions are
 * vanishingly rare (32^6 ≈ 1.07 billion) but not impossible, so it retries a
 * bounded number of times and then gives up loudly rather than looping.
 */
final class OrderNumber
{
    /**
     * @param  (callable(string): bool)|null  $exists  Overridable so the
     *                                                 generator can be unit
     *                                                 tested without a table.
     *
     * @throws RuntimeException when no free number is found in max_attempts
     */
    public static function generate(?callable $exists = null): string
    {
        $prefix = (string) config('kotiva.order_number.prefix');
        $length = (int) config('kotiva.order_number.length');
        $alphabet = (string) config('kotiva.order_number.alphabet');
        $maxAttempts = (int) config('kotiva.order_number.max_attempts');

        $exists ??= static fn (string $candidate): bool => DB::table('orders')
            ->where('order_no', $candidate)
            ->exists();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $candidate = $prefix.self::randomBody($alphabet, $length);

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Could not generate a unique order number after {$maxAttempts} attempts."
        );
    }

    /**
     * random_int, not rand/mt_rand: order numbers appear in URLs and emails,
     * so they should not be predictable from one another.
     */
    private static function randomBody(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $body = '';

        for ($i = 0; $i < $length; $i++) {
            $body .= $alphabet[random_int(0, $max)];
        }

        return $body;
    }

    /**
     * Whether a string could have been produced by this generator. Used to
     * reject obviously malformed values before hitting the database.
     */
    public static function isValid(string $value): bool
    {
        $prefix = (string) config('kotiva.order_number.prefix');
        $length = (int) config('kotiva.order_number.length');
        $alphabet = (string) config('kotiva.order_number.alphabet');

        $pattern = '/^'.preg_quote($prefix, '/').'['.preg_quote($alphabet, '/').']{'.$length.'}$/';

        return preg_match($pattern, $value) === 1;
    }
}
