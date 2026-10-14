<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Salla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * php artisan kotiva:salla-links — the only writer of products.salla_*.
 *
 * No test here reaches the network: --from and --check never call out, and
 * API mode runs against Http::fake().
 */
final class SallaLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    private const API = 'https://api.salla.dev/admin/v2';

    private const FIXTURE = 'tests/Fixtures/salla-links.json';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kotiva.salla.api_base' => self::API,
            'kotiva.salla.access_token' => '',
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function product(string $sku, bool $active = true): Product
    {
        return Product::factory()->create(['sku' => $sku, 'is_active' => $active]);
    }

    /*
    |--------------------------------------------------------------------------
    | --check
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function check_fails_while_an_active_product_is_unlinked(): void
    {
        Product::factory()->linkedToSalla()->create();
        $this->product('KOT002');

        $this->artisan('kotiva:salla-links', ['--check' => true])
            ->expectsOutputToContain('KOT002')
            ->assertExitCode(1);
    }

    #[Test]
    public function check_passes_when_every_active_product_is_linked(): void
    {
        Product::factory()->linkedToSalla()->count(3)->create();
        // An inactive product has no page to link to and does not count.
        $this->product('KOT099', active: false);

        $this->artisan('kotiva:salla-links', ['--check' => true])->assertExitCode(0);
    }

    #[Test]
    public function check_needs_no_token_and_makes_no_request(): void
    {
        Product::factory()->linkedToSalla()->create();

        $this->artisan('kotiva:salla-links', ['--check' => true])->assertExitCode(0);

        Http::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | --from
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function from_file_stores_matching_skus_and_rejects_the_rest(): void
    {
        $kot001 = $this->product('KOT001');
        $kot002 = $this->product('KOT002');
        $kot003 = $this->product('KOT003');
        $kot004 = $this->product('KOT004');

        $this->artisan('kotiva:salla-links', ['--from' => self::FIXTURE])
            ->expectsOutputToContain('unknown sku')
            ->expectsOutputToContain('url not on')
            ->expectsOutputToContain('no row in file')
            ->assertExitCode(1);

        $kot001->refresh();
        $this->assertSame(1713901761, $kot001->salla_product_id, 'id cast to int');
        $this->assertSame('/micellar-water/p1713901761', $kot001->salla_path);
        $this->assertSame($this->store().'/micellar-water/p1713901761', Salla::productUrl($kot001));
        $this->assertNotNull($kot001->salla_synced_at);

        // Lower-case SKU in the file still matches.
        $this->assertSame(172729302, $kot002->fresh()->salla_product_id);

        // URL on another host: reported, not stored.
        $this->assertNull($kot003->fresh()->salla_path);
        $this->assertNull($kot003->fresh()->salla_product_id);

        // No row for it: reported, untouched.
        $this->assertNull($kot004->fresh()->salla_path);

        // The row with no matching product created nothing.
        $this->assertSame(0, Product::query()->where('sku', 'KOT099')->count());
        $this->assertSame(0, Product::query()->where('salla_product_id', 999999999)->count());

        Http::assertNothingSent();
    }

    #[Test]
    public function from_file_succeeds_when_every_row_matches(): void
    {
        $this->product('KOT001');
        $this->product('KOT002');

        $this->artisan('kotiva:salla-links', ['--from' => self::FIXTURE, '--sku' => 'kot001'])->assertExitCode(0);

        $this->assertSame(1713901761, Product::query()->where('sku', 'KOT001')->value('salla_product_id'));
        // --sku limits the run to that one product.
        $this->assertNull(Product::query()->where('sku', 'KOT002')->value('salla_path'));
    }

    #[Test]
    public function dry_run_stores_nothing(): void
    {
        $product = $this->product('KOT001');

        $this->artisan('kotiva:salla-links', ['--from' => self::FIXTURE, '--dry-run' => true])
            ->expectsOutputToContain('would link')
            ->expectsOutputToContain('Dry run: nothing was stored.');

        $product->refresh();
        $this->assertNull($product->salla_path);
        $this->assertNull($product->salla_product_id);
        $this->assertNull($product->salla_synced_at);
    }

    #[Test]
    public function a_salla_id_already_held_by_another_product_is_refused(): void
    {
        Product::factory()->linkedToSalla(1713901761)->create(['sku' => 'KOT050']);
        $kot001 = $this->product('KOT001');

        $this->artisan('kotiva:salla-links', ['--from' => self::FIXTURE, '--sku' => 'KOT001'])
            ->expectsOutputToContain('id already linked to KOT050')
            ->assertExitCode(1);

        $this->assertNull($kot001->fresh()->salla_path);
    }

    #[Test]
    public function a_missing_or_malformed_file_fails_cleanly(): void
    {
        $this->artisan('kotiva:salla-links', ['--from' => 'tests/Fixtures/does-not-exist.json'])
            ->expectsOutputToContain('Cannot read link file')
            ->assertExitCode(1);

        $bad = tempnam(sys_get_temp_dir(), 'salla');
        file_put_contents($bad, '{"not": "a list"}');

        $this->artisan('kotiva:salla-links', ['--from' => $bad])
            ->expectsOutputToContain('must be a JSON array')
            ->assertExitCode(1);

        @unlink($bad);
    }

    #[Test]
    public function invalid_ids_are_reported_not_stored(): void
    {
        $product = $this->product('KOT001');
        $file = tempnam(sys_get_temp_dir(), 'salla');
        file_put_contents($file, (string) json_encode([
            ['sku' => 'KOT001', 'salla_product_id' => 'abc', 'salla_path' => '/x/p1'],
        ]));

        $this->artisan('kotiva:salla-links', ['--from' => $file])
            ->expectsOutputToContain('invalid: salla_product_id')
            ->assertExitCode(1);

        @unlink($file);
        $this->assertNull($product->fresh()->salla_path);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function linkFile(array $rows): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'salla');
        file_put_contents($file, (string) json_encode($rows));

        return $file;
    }

    #[Test]
    public function an_active_product_missing_from_the_file_fails_the_run(): void
    {
        $this->product('KOT001');
        $kot002 = $this->product('KOT002');
        $file = $this->linkFile([
            ['sku' => 'KOT001', 'salla_product_id' => '1713901761', 'salla_path' => '/micellar-water/p1713901761'],
        ]);

        // A dry run reports the same failure: it is the rehearsal for the
        // real run and must not pass where the real run would not.
        foreach ([[], ['--dry-run' => true]] as $extra) {
            $this->artisan('kotiva:salla-links', ['--from' => $file] + $extra)
                ->expectsOutputToContain('no row in file')
                ->expectsOutputToContain('1 active product(s) missing from the link file')
                ->assertExitCode(1);
        }

        @unlink($file);

        // The rows that were present are still stored; only the verdict changes.
        $this->assertSame('/micellar-water/p1713901761', Product::query()->where('sku', 'KOT001')->value('salla_path'));
        $this->assertNull($kot002->fresh()->salla_path);
    }

    #[Test]
    public function an_inactive_product_may_be_missing_from_the_file(): void
    {
        $this->product('KOT001');
        $this->product('KOT002', active: false);
        $file = $this->linkFile([
            ['sku' => 'KOT001', 'salla_product_id' => '1713901761', 'salla_path' => '/micellar-water/p1713901761'],
        ]);

        $this->artisan('kotiva:salla-links', ['--from' => $file])
            ->doesntExpectOutputToContain('no row in file')
            ->assertExitCode(0);

        @unlink($file);
    }

    #[Test]
    public function a_legacy_full_url_on_the_store_is_stored_as_its_path(): void
    {
        $product = $this->product('KOT001');
        $file = $this->linkFile([
            ['sku' => 'KOT001', 'salla_product_id' => '1713901761', 'salla_url' => $this->store().'/micellar-water/p1713901761'],
        ]);

        $this->artisan('kotiva:salla-links', ['--from' => $file])->assertExitCode(0);
        @unlink($file);

        $this->assertSame('/micellar-water/p1713901761', $product->fresh()->salla_path);
    }

    #[Test]
    public function a_row_on_a_different_origin_is_rejected(): void
    {
        $product = $this->product('KOT001');
        $host = (string) parse_url($this->store(), PHP_URL_HOST);

        foreach ([
            'other host' => 'https://another-store.example.test/micellar-water/p1713901761',
            'look-alike host' => 'https://'.$host.'.evil.test/micellar-water/p1713901761',
            'plain http' => 'http://'.$host.'/micellar-water/p1713901761',
        ] as $case => $url) {
            $file = $this->linkFile([['sku' => 'KOT001', 'salla_product_id' => '1713901761', 'salla_url' => $url]]);

            $this->artisan('kotiva:salla-links', ['--from' => $file])
                ->expectsOutputToContain('invalid: url not on '.$this->store())
                ->assertExitCode(1);

            @unlink($file);
            $this->assertNull($product->fresh()->salla_path, $case);
            $this->assertNull($product->fresh()->salla_product_id, $case);
        }
    }

    #[Test]
    public function a_salla_path_that_is_not_an_absolute_path_is_rejected(): void
    {
        $product = $this->product('KOT001');

        foreach (['', 'micellar-water/p1', '//evil.test/p1', 'https://evil.test/p1', '/a b/p1'] as $bad) {
            $file = $this->linkFile([['sku' => 'KOT001', 'salla_product_id' => '1713901761', 'salla_path' => $bad]]);

            $this->artisan('kotiva:salla-links', ['--from' => $file])
                ->expectsOutputToContain('salla_path must be an absolute path')
                ->assertExitCode(1);

            @unlink($file);
            $this->assertNull($product->fresh()->salla_path, var_export($bad, true));
        }
    }

    #[Test]
    public function check_rejects_a_stored_path_that_is_not_absolute(): void
    {
        Product::factory()->create(['sku' => 'KOT001', 'salla_product_id' => 1, 'salla_path' => 'micellar-water/p1']);

        $this->artisan('kotiva:salla-links', ['--check' => true])
            ->expectsOutputToContain('salla_path must be an absolute path')
            ->assertExitCode(1);
    }

    /*
    |--------------------------------------------------------------------------
    | API mode
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function sallaProduct(int $id, string $sku, string $url): array
    {
        return ['status' => 200, 'success' => true, 'data' => [
            'id' => $id,
            'sku' => $sku,
            'name' => 'Whatever',
            'urls' => ['customer' => $url, 'admin' => 'https://s.salla.sa/products/'.$id],
        ]];
    }

    #[Test]
    public function api_mode_fails_fast_without_a_token(): void
    {
        $this->product('KOT001');

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('KOTIVA_SALLA_ACCESS_TOKEN is empty')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    #[Test]
    public function api_mode_stores_the_id_and_customer_url(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(1713901761, 'kot001', $this->store().'/micellar-water/p1713901761')),
        ]);

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('linked')
            ->doesntExpectOutputToContain('test-token-value')
            ->assertExitCode(0);

        $product->refresh();
        $this->assertSame(1713901761, $product->salla_product_id);
        $this->assertSame('/micellar-water/p1713901761', $product->salla_path, 'origin stripped before storing');
        $this->assertNotNull($product->salla_synced_at);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::API.'/products/sku/KOT001'
            && $request->hasHeader('Authorization', 'Bearer test-token-value')
            && $request->hasHeader('Accept', 'application/json'));
    }

    #[Test]
    public function api_mode_refuses_a_customer_url_on_a_different_origin(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(1713901761, 'KOT001', 'https://another-store.example.test/micellar-water/p1713901761')),
        ]);

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('invalid: url not on '.$this->store())
            ->assertExitCode(1);

        $this->assertNull($product->fresh()->salla_path);
        $this->assertNull($product->fresh()->salla_product_id);
    }

    #[Test]
    public function api_mode_refuses_a_sku_mismatch(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(5, 'KOT001-B', $this->store().'/other/p5')),
        ]);

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('mismatch')
            ->assertExitCode(1);

        $this->assertNull($product->fresh()->salla_path);
    }

    #[Test]
    public function api_mode_reports_a_404_as_not_found(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response(['status' => 404, 'success' => false], 404),
        ]);

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);

        $this->assertNull($product->fresh()->salla_path);
        // A 404 is an answer, not a failure: it is not retried.
        Http::assertSentCount(1);
    }

    #[Test]
    public function api_mode_retries_a_rate_limit_and_respects_retry_after(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::sequence()
                ->push(['status' => 429], 429, ['Retry-After' => '2'])
                ->push($this->sallaProduct(1713901761, 'KOT001', $this->store().'/micellar-water/p1713901761')),
        ]);

        $this->artisan('kotiva:salla-links')->assertExitCode(0);

        Http::assertSentCount(2);
        Sleep::assertSlept(fn ($duration): bool => (int) $duration->totalMilliseconds === 2000);
        $this->assertSame(1713901761, $product->fresh()->salla_product_id);
    }

    #[Test]
    public function api_mode_gives_up_after_two_retries(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response(['status' => 500], 500),
        ]);

        $this->artisan('kotiva:salla-links')
            ->expectsOutputToContain('error: HTTP 500')
            ->assertExitCode(1);

        Http::assertSentCount(3);
        $this->assertNull($product->fresh()->salla_path);
    }

    #[Test]
    public function api_mode_dry_run_stores_nothing(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $product = $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(1713901761, 'KOT001', $this->store().'/micellar-water/p1713901761')),
        ]);

        $this->artisan('kotiva:salla-links', ['--dry-run' => true])
            ->expectsOutputToContain('would link')
            ->assertExitCode(0);

        $this->assertNull($product->fresh()->salla_path);
    }

    #[Test]
    public function api_mode_pauses_between_calls(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $this->product('KOT001');
        $this->product('KOT002');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(1, 'KOT001', $this->store().'/a/p1')),
            self::API.'/products/sku/KOT002' => Http::response($this->sallaProduct(2, 'KOT002', $this->store().'/b/p2')),
        ]);

        $this->artisan('kotiva:salla-links')->assertExitCode(0);

        Sleep::assertSlept(fn ($duration): bool => (int) $duration->totalMilliseconds === 250, 1);
    }

    #[Test]
    public function the_log_carries_sku_and_status_only(): void
    {
        config(['kotiva.salla.access_token' => 'test-token-value']);
        $this->product('KOT001');

        Http::fake([
            self::API.'/products/sku/KOT001' => Http::response($this->sallaProduct(1713901761, 'KOT001', $this->store().'/micellar-water/p1713901761')),
        ]);

        $log = Log::spy();

        $this->artisan('kotiva:salla-links')->assertExitCode(0);

        $log->shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $context === ['sku' => 'KOT001', 'status' => 'linked'])
            ->once();
        $log->shouldNotHaveReceived('debug');
        $log->shouldNotHaveReceived('error');
    }
}
