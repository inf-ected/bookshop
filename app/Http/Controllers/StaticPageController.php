<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class StaticPageController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_PAGES = [
        'about',
        'privacy',
        'terms',
        'offer',
        'personal-data',
        'newsletter-consent',
        'cookies',
        'refund',
        'contacts',
        'payment-info',
    ];

    public function show(string $page): View
    {
        if (! in_array($page, self::ALLOWED_PAGES, strict: true)) {
            abort(404);
        }

        return view("static.{$page}");
    }
}
