<?php

declare(strict_types=1);

namespace App\Features\Admin\Services\Converters;

use App\Enums\BookFileFormat;
use App\Features\Admin\Contracts\FormatConverter;
use App\Features\Admin\Exceptions\ConversionException;
use Symfony\Component\Process\Process;

class PandocConverter implements FormatConverter
{
    public function supports(BookFileFormat $from, BookFileFormat $to): bool
    {
        return $from === BookFileFormat::Docx && $to === BookFileFormat::Epub;
    }

    public function convert(string $inputPath, string $outputPath, BookFileFormat $from, BookFileFormat $to): void
    {
        $process = new Process(['pandoc', $inputPath, '-o', $outputPath]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ConversionException(
                sprintf('pandoc failed: %s', $process->getErrorOutput()),
            );
        }
    }
}
