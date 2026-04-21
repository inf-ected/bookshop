<?php

declare(strict_types=1);

namespace App\Features\Admin\Services\Converters;

use App\Enums\BookFileFormat;
use App\Features\Admin\Contracts\FormatConverter;
use App\Features\Admin\Exceptions\ConversionException;
use Symfony\Component\Process\Process;

class CalibreConverter implements FormatConverter
{
    public function supports(BookFileFormat $from, BookFileFormat $to): bool
    {
        return match (true) {
            $from === BookFileFormat::Docx && $to === BookFileFormat::Fb2 => true,
            $from === BookFileFormat::Epub && $to === BookFileFormat::Fb2 => true,
            $from === BookFileFormat::Fb2 && $to === BookFileFormat::Epub => true,
            default => false,
        };
    }

    public function convert(string $inputPath, string $outputPath, BookFileFormat $from, BookFileFormat $to): void
    {
        $process = new Process(['ebook-convert', $inputPath, $outputPath]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ConversionException(
                sprintf('ebook-convert failed: %s', $process->getErrorOutput()),
            );
        }
    }
}
