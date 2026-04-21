<?php

declare(strict_types=1);

namespace App\Enums;

enum BookFileFormat: string
{
    case Docx = 'docx';
    case Epub = 'epub';
    case Fb2 = 'fb2';

    /**
     * Whether this format can be delivered to clients.
     * DOCX is source-only and must never be delivered to clients.
     * This is the single authoritative gate for the DOCX protection rule.
     */
    public function isClientAccessible(): bool
    {
        return match ($this) {
            self::Epub, self::Fb2 => true,
            self::Docx => false,
        };
    }

    /**
     * Returns all client-deliverable formats.
     *
     * @return array<int, self>
     */
    public static function clientAccessible(): array
    {
        return [self::Epub, self::Fb2];
    }

    /** Human-readable label for admin UI. */
    public function label(): string
    {
        return match ($this) {
            self::Docx => 'DOCX',
            self::Epub => 'EPUB',
            self::Fb2 => 'FB2',
        };
    }

    /** File extension string. */
    public function extension(): string
    {
        return $this->value;
    }

    /** MIME type for the format. */
    public function mimeType(): string
    {
        return match ($this) {
            self::Docx => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::Epub => 'application/epub+zip',
            self::Fb2 => 'application/x-fictionbook+xml',
        };
    }
}
