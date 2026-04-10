<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $s3PublicOrigin = $this->extractOrigin(config('filesystems.disks.s3-public.url', ''));

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com https://js.stripe.com",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: {$s3PublicOrigin}",
                "font-src 'self'",
                "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://api.stripe.com",
                "frame-ancestors 'none'",
            ])
        );

        return $response;
    }

    private function extractOrigin(string $url): string
    {
        if ($url === '') {
            return "'self'";
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';

        return "{$scheme}://{$host}{$port}";
    }
}
