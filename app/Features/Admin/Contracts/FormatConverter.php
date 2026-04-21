<?php

declare(strict_types=1);

namespace App\Features\Admin\Contracts;

use App\Enums\BookFileFormat;
use App\Features\Admin\Exceptions\ConversionException;

interface FormatConverter
{
    /**
     * Convert a file from one format to another.
     *
     * @throws ConversionException on non-zero exit or process failure
     */
    public function convert(string $inputPath, string $outputPath, BookFileFormat $from, BookFileFormat $to): void;

    /** Whether this converter supports the given format pair. */
    public function supports(BookFileFormat $from, BookFileFormat $to): bool;
}
