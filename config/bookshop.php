<?php

declare(strict_types=1);

use App\Enums\BookFileFormat;

return [

    /*
    |--------------------------------------------------------------------------
    | Download URL TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a pre-signed S3 download URL remains valid after generation.
    | Keep this short — the URL grants unauthenticated access to the epub file.
    | Default: 300 seconds (5 minutes). Set via DOWNLOAD_URL_TTL env variable.
    |
    */

    'download_url_ttl' => (int) env('DOWNLOAD_URL_TTL', 300),

    'formats' => [
        'conversion_matrix' => [
            BookFileFormat::Docx->value => [BookFileFormat::Epub->value, BookFileFormat::Fb2->value],
            BookFileFormat::Epub->value => [BookFileFormat::Fb2->value],
            BookFileFormat::Fb2->value => [BookFileFormat::Epub->value],
        ],

        'converter_preference' => [
            BookFileFormat::Docx->value.'_to_'.BookFileFormat::Epub->value => 'pandoc',
            BookFileFormat::Docx->value.'_to_'.BookFileFormat::Fb2->value => 'calibre',
            BookFileFormat::Epub->value.'_to_'.BookFileFormat::Fb2->value => 'calibre',
            BookFileFormat::Fb2->value.'_to_'.BookFileFormat::Epub->value => 'calibre',
        ],
    ],

];
