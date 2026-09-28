<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A product has fallen to or below its low-stock threshold.
 *
 * Fired on the decrement that crosses the line, not on every decrement while
 * below it — otherwise a busy morning would send one alert per sale. The
 * listener debounces further, once per product per 24h (§6.8).
 */
final class StockLow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly int $remaining,
    ) {}
}
