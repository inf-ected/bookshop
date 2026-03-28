<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaticPageControllerTest extends TestCase
{
    /**
     * @return list<array{string}>
     */
    public static function staticPageProvider(): array
    {
        return [
            ['about'],
            ['privacy'],
            ['terms'],
            ['offer'],
            ['personal-data'],
            ['newsletter-consent'],
            ['cookies'],
            ['refund'],
            ['contacts'],
            ['payment-info'],
        ];
    }

    #[DataProvider('staticPageProvider')]
    public function test_static_pages_return_200(string $slug): void
    {
        $response = $this->get("/{$slug}");

        $response->assertStatus(200);
    }

    public function test_unknown_static_page_returns_404(): void
    {
        $response = $this->get('/unknown-page');

        $response->assertStatus(404);
    }
}
