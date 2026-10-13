<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LlmsTxt;
use Illuminate\Console\Command;

/**
 * Prints (or exports) llms.txt from the catalog.
 *
 * The file used to be hand-maintained and pointed AI crawlers at 25 product
 * URLs that no longer exist. It is now rendered by App\Support\LlmsTxt and
 * served live at /llms.txt, so there is no public/llms.txt to keep in sync.
 * This command shows what the route will serve, or writes a copy elsewhere
 * with --output. Re-check it after `kotiva:salla-links`.
 */
final class GenerateLlmsTxt extends Command
{
    protected $signature = 'kotiva:llms-txt
        {--origin= : Main website origin (defaults to config app.url)}
        {--output= : Write to this path, relative to the project root, instead of printing}';

    protected $description = 'Print llms.txt (served at /llms.txt) from the catalog and Salla links';

    public function handle(): int
    {
        $origin = $this->option('origin');
        $llms = LlmsTxt::render(is_string($origin) ? $origin : null);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents(base_path($output), $llms['text']);
            $this->info("Wrote {$output} ({$llms['count']} products).");
        } else {
            $this->line($llms['text']);
        }

        if ($llms['unlinked'] > 0) {
            $this->warn("{$llms['unlinked']} active product(s) have no Salla link and point at the store root. Run kotiva:salla-links first.");
        }

        return self::SUCCESS;
    }
}
