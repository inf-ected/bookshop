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

        $s3PublicOrigin = $this->extractOrigin(config('filesystems.disks.s3-public.url') ?? '');

        $isLocal = app()->isLocal();
        // HTTP origin for script/style/font directives; WebSocket added separately for connect-src.
        $viteHttp = $isLocal ? ' http://localhost:5173' : '';
        $viteConnect = $isLocal ? ' http://localhost:5173 ws://localhost:5173' : '';

        // Alpine.js uses new AsyncFunction() in Vite dev mode — requires unsafe-eval in local only.
        $unsafeEval = $isLocal ? " 'unsafe-eval'" : '';

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com")');
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'{$unsafeEval} https://www.googletagmanager.com https://www.google-analytics.com https://js.stripe.com{$viteHttp}",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$viteHttp}",
                "img-src 'self' data: {$s3PublicOrigin}",
                "font-src 'self' https://fonts.bunny.net{$viteHttp}",
                "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://api.stripe.com{$viteConnect}",
                "frame-ancestors 'none'",
            ])
        );

        return $response;
    }

    private function extractOrigin(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';

        return "{$scheme}://{$host}{$port}";
    }
}
