<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Salla;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use JsonException;
use Throwable;

/**
 * Links each catalog product to its page on the Salla storefront, by SKU.
 *
 * Three modes:
 *
 *   --from=<file>  The standard way. Reads an exported JSON list of
 *                  {sku, salla_product_id, salla_path} rows — the committed
 *                  storage/app/salla-links.json — and needs no API token.
 *                  A legacy full salla_url is accepted when it is on the
 *                  configured store; its origin is stripped. Every active
 *                  product (within --sku) must have a row: one that is
 *                  missing fails the run, --dry-run included.
 *   (no option)    Asks the Salla Merchant API for each SKU. Needs
 *                  KOTIVA_SALLA_ACCESS_TOKEN with products.read.
 *   --check        Touches nothing. Lists active products with no valid Salla
 *                  path and exits 1 if there are any; deploy runs this.
 *
 * Only the path is stored ("/micellar-water/p1713901761"). The store origin
 * lives in .env and is joined to it by Salla::productUrl() alone.
 *
 * The token is read from config and sent in one header; it is never printed,
 * logged or stored. The log carries SKU and status only — never a response
 * body, which can include merchant data that has no business in a log file.
 */
final class SyncSallaLinks extends Command
{
    protected $signature = 'kotiva:salla-links
        {--from= : Read links from a JSON file instead of the API}
        {--check : Verify only, exit 1 if any active product is unlinked}
        {--sku= : Sync one SKU}
        {--dry-run : Show what would be stored without storing it}';

    protected $description = 'Link products to their Salla storefront pages by SKU';

    private const STATUS_LINKED = 'linked';

    private const STATUS_WOULD_LINK = 'would link';

    private const STATUS_NOT_FOUND = 'not found';

    private const STATUS_MISMATCH = 'mismatch';

    private const STATUS_ERROR = 'error';

    private const STATUS_UNKNOWN_SKU = 'unknown sku';

    private const STATUS_INVALID = 'invalid';

    private const STATUS_UNLINKED = 'unlinked';

    private const BAD_PATH = 'salla_path must be an absolute path starting with "/"';

    /** Pause between API calls, so a 25-product sync stays well inside Salla's rate limit. */
    private const API_PAUSE_MS = 250;

    /** Never wait longer than this on a Retry-After, whatever the header says. */
    private const MAX_RETRY_AFTER_MS = 30_000;

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private array $rows = [];

    public function handle(): int
    {
        if ((bool) $this->option('check')) {
            return $this->check();
        }

        $from = $this->option('from');

        if (is_string($from) && $from !== '') {
            return $this->syncFromFile($from);
        }

        return $this->syncFromApi();
    }

    /*
    |--------------------------------------------------------------------------
    | --check
    |--------------------------------------------------------------------------
    */

    private function check(): int
    {
        $unlinked = $this->scopedProducts(activeOnly: true)
            ->filter(fn (Product $p): bool => ! Salla::isStorePath($p->salla_path));

        if ($unlinked->isEmpty()) {
            $this->info('Every active product is linked to Salla.');

            return self::SUCCESS;
        }

        foreach ($unlinked as $product) {
            $status = blank($product->salla_path)
                ? self::STATUS_UNLINKED
                : self::STATUS_INVALID.': '.self::BAD_PATH;

            $this->addRow($product->sku, $product->name, null, $product->salla_path, $status);
        }

        $this->printTable();
        $this->error($unlinked->count().' active product(s) have no Salla link. Run: php artisan kotiva:salla-links --from=storage/app/salla-links.json');

        return self::FAILURE;
    }

    /*
    |--------------------------------------------------------------------------
    | --from=<file>
    |--------------------------------------------------------------------------
    */

    private function syncFromFile(string $path): int
    {
        $rows = $this->readLinkFile($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        $only = $this->skuOption();
        $dryRun = (bool) $this->option('dry-run');
        $products = $this->productsBySku();
        $seenSkus = [];
        $seenIds = [];
        $failed = false;

        foreach ($rows as $index => $row) {
            $sku = is_array($row) && is_string($row['sku'] ?? null) ? strtoupper(trim($row['sku'])) : '';
            $rawId = is_array($row) ? ($row['salla_product_id'] ?? null) : null;
            [$path, $url, $pathProblem] = $this->rowPath($row);
            $name = is_array($row) && is_string($row['name'] ?? null) ? $row['name'] : '';

            if ($only !== null && $sku !== $only) {
                continue;
            }

            if ($sku === '') {
                $this->addRow('#'.($index + 1), $name, null, $url, self::STATUS_INVALID.': no sku');
                $failed = true;

                continue;
            }

            $id = $this->parseId($rawId);

            if ($id === null) {
                $this->addRow($sku, $name, null, $url, self::STATUS_INVALID.': salla_product_id');
                $failed = true;

                continue;
            }

            if ($path === null) {
                $this->addRow($sku, $name, (string) $id, $url, self::STATUS_INVALID.': '.$pathProblem);
                $failed = true;

                continue;
            }

            $url = $path;

            if (isset($seenSkus[$sku]) || isset($seenIds[$id])) {
                $this->addRow($sku, $name, (string) $id, $url, self::STATUS_INVALID.': duplicate row');
                $failed = true;

                continue;
            }

            $seenSkus[$sku] = true;
            $seenIds[$id] = true;

            $product = $products[$sku] ?? null;

            if (! $product instanceof Product) {
                $this->addRow($sku, $name, (string) $id, $url, self::STATUS_UNKNOWN_SKU);
                $this->logStatus($sku, self::STATUS_UNKNOWN_SKU);
                $failed = true;

                continue;
            }

            $status = $this->store($product, $id, $path, $dryRun);
            $failed = $failed || $status !== self::STATUS_LINKED && $status !== self::STATUS_WOULD_LINK;
            $this->addRow($product->sku, $product->name, (string) $id, $url, $status);
        }

        /*
         | The other direction: active catalog products the file says nothing
         | about. Each one fails the run. A file that silently drops a product
         | would leave that product with no "Buy" link (or a stale one), which
         | `--check` catches only after deploy. Inactive products are not
         | sold, so they are allowed to be absent.
         */
        $missing = 0;

        foreach ($products as $sku => $product) {
            if (($only === null || $sku === $only) && ! isset($seenSkus[$sku]) && $product->is_active) {
                $this->addRow($product->sku, $product->name, null, null, 'no row in file');
                $missing++;
                $failed = true;
            }
        }

        if ($only !== null && ! isset($products[$only])) {
            $this->error("No product with SKU [{$only}].");
            $failed = true;
        }

        $this->printTable();

        if ($missing > 0) {
            $this->error($missing.' active product(s) missing from the link file.');
        }

        if ($dryRun) {
            $this->comment('Dry run: nothing was stored.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The row's store path, from salla_path or (legacy) a full salla_url on
     * the configured store: [path or null, value to display, problem].
     *
     * @return array{0: string|null, 1: string, 2: string}
     */
    private function rowPath(mixed $row): array
    {
        $rawPath = is_array($row) ? ($row['salla_path'] ?? null) : null;
        $rawUrl = is_array($row) ? ($row['salla_url'] ?? null) : null;

        if (is_string($rawPath)) {
            $path = trim($rawPath);

            return Salla::isStorePath($path) ? [$path, $path, ''] : [null, $path, self::BAD_PATH];
        }

        if (is_string($rawUrl)) {
            $url = trim($rawUrl);

            // Another origin is refused, never re-homed onto ours: the "Buy"
            // button would land on a store this site does not use.
            return [Salla::pathFromStoreUrl($url), $url, 'url not on '.Salla::storeUrl(withReferral: false)];
        }

        return [null, '', 'no salla_path'];
    }

    /**
     * @return array<int, mixed>|null
     */
    private function readLinkFile(string $path): ?array
    {
        $resolved = $this->isAbsolutePath($path) ? $path : base_path($path);

        if (! is_file($resolved) || ! is_readable($resolved)) {
            $this->error("Cannot read link file [{$path}].");

            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($resolved), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Link file [{$path}] is not valid JSON: {$e->getMessage()}");

            return null;
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            $this->error("Link file [{$path}] must be a JSON array of rows.");

            return null;
        }

        return $decoded;
    }

    /*
    |--------------------------------------------------------------------------
    | API mode
    |--------------------------------------------------------------------------
    */

    private function syncFromApi(): int
    {
        $token = (string) config('kotiva.salla.access_token');

        if ($token === '') {
            $this->error('KOTIVA_SALLA_ACCESS_TOKEN is empty. Set a Salla Merchant API token with products.read in .env, or use --from=storage/app/salla-links.json.');

            return self::FAILURE;
        }

        $only = $this->skuOption();
        $products = $only === null
            ? $this->scopedProducts(activeOnly: true)
            : $this->scopedProducts(activeOnly: false);

        if ($products->isEmpty()) {
            $this->error($only === null ? 'There are no active products to link.' : "No product with SKU [{$only}].");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $failed = false;
        $first = true;

        foreach ($products as $product) {
            if (! $first) {
                Sleep::for(self::API_PAUSE_MS)->milliseconds();
            }

            $first = false;

            [$status, $id, $url] = $this->lookup($product, $token);

            if ($status === self::STATUS_LINKED && $id !== null && $url !== null) {
                $path = Salla::pathFromStoreUrl($url);

                if ($path === null) {
                    // Only the path is stored, so a page on another origin
                    // would be silently re-homed onto the configured store.
                    // Refuse it; a changed store domain belongs in .env.
                    $this->logStatus($product->sku, self::STATUS_INVALID);
                    $status = self::STATUS_INVALID.': url not on '.Salla::storeUrl();
                } else {
                    $url = $path;
                    $status = $this->store($product, $id, $path, $dryRun);
                }
            }

            $failed = $failed || ! in_array($status, [self::STATUS_LINKED, self::STATUS_WOULD_LINK], true);
            $this->addRow($product->sku, $product->name, $id === null ? null : (string) $id, $url, $status);
        }

        $this->printTable();

        if ($dryRun) {
            $this->comment('Dry run: nothing was stored.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     */
    private function lookup(Product $product, string $token): array
    {
        $base = (string) config('kotiva.salla.api_base');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->retry(
                    3, // the first attempt plus two retries
                    fn (int $attempt, Throwable $e): int => $this->retryDelay($attempt, $e),
                    fn (Throwable $e): bool => $e instanceof RequestException && $this->isRetryable($e->response),
                    throw: false,
                )
                ->get($base.'/products/sku/'.rawurlencode($product->sku));
        } catch (ConnectionException) {
            $this->logStatus($product->sku, self::STATUS_ERROR);

            return [self::STATUS_ERROR.': connection', null, null];
        }

        if ($response->status() === 404) {
            $this->logStatus($product->sku, self::STATUS_NOT_FOUND);

            return [self::STATUS_NOT_FOUND, null, null];
        }

        if (! $response->successful()) {
            $this->logStatus($product->sku, self::STATUS_ERROR);

            return [self::STATUS_ERROR.': HTTP '.$response->status(), null, null];
        }

        $id = $this->parseId($response->json('data.id'));
        $url = $response->json('data.urls.customer');
        $remoteSku = $response->json('data.sku');

        if (! is_string($remoteSku) || strcasecmp(trim($remoteSku), $product->sku) !== 0) {
            $this->logStatus($product->sku, self::STATUS_MISMATCH);

            return [self::STATUS_MISMATCH.': Salla SKU '.(is_scalar($remoteSku) ? (string) $remoteSku : '—'), $id, null];
        }

        if ($id === null || ! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->logStatus($product->sku, self::STATUS_ERROR);

            return [self::STATUS_ERROR.': incomplete response', $id, null];
        }

        return [self::STATUS_LINKED, $id, $url];
    }

    private function isRetryable(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function retryDelay(int $attempt, Throwable $e): int
    {
        if ($e instanceof RequestException) {
            $header = $e->response->header('Retry-After');

            if (is_numeric($header)) {
                return (int) min(self::MAX_RETRY_AFTER_MS, max(0, (int) $header) * 1000);
            }
        }

        return 500 * $attempt;
    }

    /*
    |--------------------------------------------------------------------------
    | Shared
    |--------------------------------------------------------------------------
    */

    /**
     * Write one link, refusing to steal a Salla id another product holds.
     */
    private function store(Product $product, int $id, string $path, bool $dryRun): string
    {
        $holder = Product::query()
            ->withTrashed()
            ->where('salla_product_id', $id)
            ->whereKeyNot($product->getKey())
            ->value('sku');

        if ($holder !== null) {
            $this->logStatus($product->sku, self::STATUS_INVALID);

            return self::STATUS_INVALID.": id already linked to {$holder}";
        }

        if ($dryRun) {
            return self::STATUS_WOULD_LINK;
        }

        $product->forceFill([
            'salla_product_id' => $id,
            'salla_path' => $path,
            'salla_synced_at' => now(),
        ])->save();

        $this->logStatus($product->sku, self::STATUS_LINKED);

        return self::STATUS_LINKED;
    }

    /**
     * Every product keyed by upper-cased SKU, so matching is case-insensitive.
     *
     * @return array<string, Product>
     */
    private function productsBySku(): array
    {
        $out = [];

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $out[strtoupper($product->sku)] = $product;
        }

        return $out;
    }

    /**
     * @return Collection<int, Product>
     */
    private function scopedProducts(bool $activeOnly): Collection
    {
        $only = $this->skuOption();

        return Product::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->when($only !== null, fn ($q) => $q->whereRaw('UPPER(sku) = ?', [$only]))
            ->orderBy('id')
            ->get();
    }

    private function skuOption(): ?string
    {
        $sku = $this->option('sku');

        return is_string($sku) && trim($sku) !== '' ? strtoupper(trim($sku)) : null;
    }

    private function parseId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^[1-9]\d{0,18}$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function addRow(string $sku, string $name, ?string $id, ?string $link, string $status): void
    {
        $this->rows[] = [$sku, $name, $id ?? '—', $link === null || $link === '' ? '—' : $link, $status];
    }

    private function printTable(): void
    {
        $this->table(['SKU', 'Name', 'Salla ID', 'Salla path', 'Status'], $this->rows);
    }

    private function logStatus(string $sku, string $status): void
    {
        Log::info('kotiva:salla-links', ['sku' => $sku, 'status' => $status]);
    }
}
