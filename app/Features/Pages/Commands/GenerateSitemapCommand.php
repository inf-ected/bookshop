<?php

declare(strict_types=1);

namespace App\Features\Pages\Commands;

use App\Features\Pages\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'app:generate-sitemap';

    protected $description = 'Generate and cache the sitemap.xml';

    public function handle(SitemapController $controller): int
    {
        Cache::forget('sitemap.xml');

        $xml = $controller->buildSitemap()->render();

        Cache::put('sitemap.xml', $xml, now()->addHours(24));

        $this->info('Sitemap regenerated successfully.');

        return self::SUCCESS;
    }
}
