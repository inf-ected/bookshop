<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Security headers
    // -------------------------------------------------------------------------

    public function test_security_headers_present_on_homepage(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_x_frame_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_content_security_policy_contains_required_directives(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    // -------------------------------------------------------------------------
    // Rate limiting — login
    // -------------------------------------------------------------------------

    public function test_login_rate_limiter_triggers_429(): void
    {
        RateLimiter::clear('login');

        $payload = [
            '_token' => csrf_token(),
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ];

        // First 10 requests should not be rate-limited (they may fail auth, but not 429)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withoutMiddleware(SecurityHeaders::class)
                ->post('/login', $payload);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be rate-limited");
        }

        // 11th request must be rate-limited
        $response = $this->withoutMiddleware(SecurityHeaders::class)
            ->post('/login', $payload);
        $response->assertStatus(429);
    }

    // -------------------------------------------------------------------------
    // Rate limiting — checkout
    // -------------------------------------------------------------------------

    public function test_checkout_rate_limiter_triggers_429(): void
    {
        RateLimiter::clear('checkout');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // First 5 requests should not be rate-limited (they may fail validation, but not 429)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)
                ->withoutMiddleware(SecurityHeaders::class)
                ->post('/checkout', []);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be rate-limited");
        }

        // 6th request must be rate-limited
        $response = $this->actingAs($user)
            ->withoutMiddleware(SecurityHeaders::class)
            ->post('/checkout', []);
        $response->assertStatus(429);
    }
}
