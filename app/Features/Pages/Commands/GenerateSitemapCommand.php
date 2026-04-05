<?php

declare(strict_types=1);

namespace App\Features\Pages\Commands;

use App\Features\Pages\Services\SitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'app:generate-sitemap';

    protected $description = 'Generate and cache the sitemap.xml';

    public function handle(SitemapService $sitemapService): int
    {
        Cache::forget('sitemap.xml');

        $xml = $sitemapService->build()->render();

        Cache::put('sitemap.xml', $xml, now()->addHours(24));

        $this->info('Sitemap regenerated successfully.');

        return self::SUCCESS;
    }
}
