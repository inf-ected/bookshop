<?php

declare(strict_types=1);

namespace App\Features\Admin\Services;

use App\Enums\BookFileFormat;
use App\Enums\BookFileStatus;
use App\Features\Admin\Contracts\FormatConverter;
use App\Features\Admin\Exceptions\ConversionException;
use App\Features\Admin\Jobs\ConvertBookFormat;
use App\Features\Admin\Services\Converters\CalibreConverter;
use App\Features\Admin\Services\Converters\PandocConverter;
use App\Models\BookFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookConversionService
{
    /**
     * Resolve the preferred converter for a given format pair.
     *
     * @throws \RuntimeException when no converter supports the pair
     */
    public function resolveConverter(BookFileFormat $from, BookFileFormat $to): FormatConverter
    {
        $key = "{$from->value}_to_{$to->value}";
        $preference = config("bookshop.formats.converter_preference.{$key}");

        $converters = [
            'pandoc' => new PandocConverter,
            'calibre' => new CalibreConverter,
        ];

        if ($preference !== null && isset($converters[$preference])) {
            $converter = $converters[$preference];

            if ($converter->supports($from, $to)) {
                return $converter;
            }
        }

        // Fall back to Calibre as default, then Pandoc.
        foreach ([new CalibreConverter, new PandocConverter] as $converter) {
            if ($converter->supports($from, $to)) {
                return $converter;
            }
        }

        throw new \RuntimeException(
            "No converter supports {$from->value} → {$to->value}.",
        );
    }

    /**
     * Read the conversion matrix for the source format, create/reset target BookFile
     * records, and dispatch ConvertBookFormat jobs.
     */
    public function dispatchConversions(BookFile $source): void
    {
        $matrix = config('bookshop.formats.conversion_matrix', []);
        $targets = $matrix[$source->format->value] ?? [];

        foreach ($targets as $targetValue) {
            $targetFormat = BookFileFormat::from($targetValue);

            $target = BookFile::query()
                ->where('book_id', $source->book_id)
                ->where('format', $targetFormat)
                ->first();

            if ($target instanceof BookFile) {
                if ($target->path !== null) {
                    Storage::disk('s3-private')->delete($target->path);
                }

                $target->update([
                    'status' => BookFileStatus::Pending,
                    'path' => null,
                    'error_message' => null,
                ]);
            } else {
                $target = BookFile::create([
                    'book_id' => $source->book_id,
                    'format' => $targetFormat,
                    'status' => BookFileStatus::Pending,
                    'is_source' => false,
                ]);
            }

            ConvertBookFormat::dispatch($target->id, $source->id);
        }
    }

    /**
     * Download the source from S3, run conversion, upload result, update target status.
     *
     * Called by ConvertBookFormat job after it has validated pre-conditions.
     */
    public function executeConversion(BookFile $source, BookFile $target): void
    {
        $tmpDir = sys_get_temp_dir();
        $sourceExt = $source->format->extension();
        $targetExt = $target->format->extension();

        $sourceTmp = $tmpDir.'/'.Str::uuid().'.'.$sourceExt;
        $outputTmp = $tmpDir.'/'.Str::uuid().'.'.$targetExt;

        try {
            $stream = Storage::disk('s3-private')->readStream($source->path);

            if ($stream === null) {
                throw new ConversionException(
                    "Source file could not be downloaded from S3: {$source->path}"
                );
            }

            file_put_contents($sourceTmp, $stream);
            fclose($stream);

            $converter = $this->resolveConverter($source->format, $target->format);
            $converter->convert($sourceTmp, $outputTmp, $source->format, $target->format);

            $s3Path = "books/{$target->book_id}/".Str::uuid().'.'.$targetExt;
            Storage::disk('s3-private')->put($s3Path, fopen($outputTmp, 'r'));

            $target->update([
                'path' => $s3Path,
                'status' => BookFileStatus::Ready,
            ]);
        } finally {
            if (file_exists($sourceTmp)) {
                unlink($sourceTmp);
            }
            if (file_exists($outputTmp)) {
                unlink($outputTmp);
            }
        }
    }
}
