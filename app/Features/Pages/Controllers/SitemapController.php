<?php

declare(strict_types=1);

namespace App\Features\Pages\Controllers;

use App\Features\Pages\Services\SitemapService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __construct(private readonly SitemapService $sitemapService) {}

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(24), function (): string {
            return $this->sitemapService->build()->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        return response()
            ->view('seo.robots')
            ->header('Content-Type', 'text/plain');
    }
}
