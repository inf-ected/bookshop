User-agent: *
Disallow: /admin
Disallow: /cabinet
Disallow: /checkout
Disallow: /cart
Disallow: /api/
Disallow: /sanctum/
Disallow: /cgi-bin/
Disallow: /auth/
Disallow: /webhooks/
Disallow: /books/*/fragment

Sitemap: {{ config('app.url') }}/sitemap.xml
