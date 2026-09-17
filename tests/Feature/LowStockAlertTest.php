<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementReason;
use App\Events\StockLow;
use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Models\Setting;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §6.8's low-stock alert, and the two things that make it useful rather than
 * noise: it fires on the decrement that CROSSES the threshold, and it is
 * debounced to once per product per 24 hours.
 */
final class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->stock = app(StockService::class);
    }

    private function product(int $stock, int $threshold = 5): Product
    {
        $product = Product::factory()->withStock(0)->create(['low_stock_threshold' => $threshold]);
        $this->stock->adjust($product, $stock, StockMovementReason::Restock, 'Opening stock');

        return $product->fresh();
    }

    #[Test]
    public function crossing_the_threshold_raises_the_event(): void
    {
        Event::fake([StockLow::class]);

        $product = $this->product(stock: 6, threshold: 5);

        // 6 -> 5 crosses the line.
        $this->stock->reserve($product, 1);

        Event::assertDispatched(
            StockLow::class,
            fn (StockLow $e): bool => $e->product->is($product) && $e->remaining === 5
        );
    }

    #[Test]
    public function staying_above_the_threshold_raises_nothing(): void
    {
        Event::fake([StockLow::class]);

        $product = $this->product(stock: 20, threshold: 5);
        $this->stock->reserve($product, 3);

        Event::assertNotDispatched(StockLow::class);
    }

    #[Test]
    public function further_sales_below_the_threshold_do_not_re_raise_the_event(): void
    {
        // Otherwise a busy morning sends one alert per sale.
        $product = $this->product(stock: 6, threshold: 5);
        $this->stock->reserve($product, 1);

        Event::fake([StockLow::class]);
        $this->stock->reserve($product->fresh(), 1);
        $this->stock->reserve($product->fresh(), 1);

        Event::assertNotDispatched(StockLow::class);
    }

    #[Test]
    public function the_alert_is_emailed_to_the_configured_recipient(): void
    {
        Setting::put('low_stock_alert_email', 'ops@kotiva.test');

        $product = $this->product(stock: 6, threshold: 5);
        $this->stock->reserve($product, 1);

        Mail::assertQueued(
            LowStockAlert::class,
            fn (LowStockAlert $mail): bool => $mail->hasTo('ops@kotiva.test')
                && $mail->product->is($product)
                && $mail->remaining === 5
        );
    }

    #[Test]
    public function the_alert_is_debounced_per_product(): void
    {
        $product = $this->product(stock: 6, threshold: 5);

        // Cross, restock above the line, then cross again in the same window.
        $this->stock->reserve($product, 1);
        $this->stock->adjust($product->fresh(), 10, StockMovementReason::Restock);
        $this->stock->reserve($product->fresh(), 10);

        Mail::assertQueuedCount(1);
    }

    #[Test]
    public function a_different_product_is_alerted_independently(): void
    {
        $a = $this->product(stock: 6, threshold: 5);
        $b = $this->product(stock: 6, threshold: 5);

        $this->stock->reserve($a, 1);
        $this->stock->reserve($b, 1);

        Mail::assertQueuedCount(2);
    }

    #[Test]
    public function the_debounce_expires(): void
    {
        $product = $this->product(stock: 6, threshold: 5);
        $this->stock->reserve($product, 1);

        // Simulate the window passing.
        Cache::forget('kotiva.low_stock_alert.'.$product->getKey());

        $this->stock->adjust($product->fresh(), 10, StockMovementReason::Restock);
        $this->stock->reserve($product->fresh(), 10);

        Mail::assertQueuedCount(2);
    }

    #[Test]
    public function selling_out_completely_still_alerts(): void
    {
        $product = $this->product(stock: 6, threshold: 5);

        // One decrement straight through the threshold to zero.
        $this->stock->reserve($product, 6);

        Mail::assertQueued(
            LowStockAlert::class,
            fn (LowStockAlert $mail): bool => $mail->remaining === 0
        );
    }
}
