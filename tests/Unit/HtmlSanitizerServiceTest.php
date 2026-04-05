<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Blog\Services\HtmlSanitizerService;
use Tests\TestCase;

class HtmlSanitizerServiceTest extends TestCase
{
    private HtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizerService;
    }

    public function test_strips_script_tags(): void
    {
        $dirty = '<p>Текст</p><script>alert("xss")</script>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>Текст</p>', $clean);
    }

    public function test_strips_onclick_attributes(): void
    {
        $dirty = '<p onclick="alert(1)">Параграф</p>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('Параграф', $clean);
    }

    public function test_allows_safe_html_tags(): void
    {
        $safe = '<p>Текст</p><strong>Жирный</strong><em>Курсив</em><ul><li>Элемент</li></ul>';
        $clean = $this->sanitizer->sanitize($safe);

        $this->assertStringContainsString('<p>Текст</p>', $clean);
        $this->assertStringContainsString('<strong>Жирный</strong>', $clean);
        $this->assertStringContainsString('<em>Курсив</em>', $clean);
        $this->assertStringContainsString('<li>Элемент</li>', $clean);
    }

    public function test_strips_iframe_tags(): void
    {
        $dirty = '<p>Контент</p><iframe src="https://evil.com"></iframe>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('Контент', $clean);
    }

    public function test_allows_links_with_href(): void
    {
        $html = '<a href="https://example.com" title="Ссылка">Нажми</a>';
        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('Нажми', $clean);
    }

    public function test_strips_javascript_href(): void
    {
        $dirty = '<a href="javascript:alert(1)">Кликни</a>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_empty_string_returns_empty(): void
    {
        $clean = $this->sanitizer->sanitize('');

        $this->assertSame('', $clean);
    }
}
