<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Download URL TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a pre-signed S3 download URL remains valid after generation.
    | Keep this short — the URL grants unauthenticated access to the epub file.
    |
    */

    'download_url_ttl' => (int) env('DOWNLOAD_URL_TTL', 5),

];
