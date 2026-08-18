<?php

declare(strict_types=1);

namespace Brix\CelumFal\Utility\FileInfo;

enum Format
{
    case THUMBNAIL;
    case PREVIEW;
    case LARGE_PREVIEW;
    case VIDEO;
    case PDF;
    case OTHER;

    public function formatToString(?Format $format = null): string
    {
        return match($format ?? $this)
        {
            self::THUMBNAIL => 'THUMB',
            self::PREVIEW => 'PREVIEW',
            self::LARGE_PREVIEW => 'LARGEPREVIEW',
            self::PDF => 'PDF',
            self::VIDEO => 'VIDEO',
            default => '',
        };
    }

    public static function getFormatByString(string $stringFormat): Format
    {
        return match($stringFormat)
        {
            'THUMB' => self::THUMBNAIL,
            'PREVIEW' => self::PREVIEW,
            'LARGEPREVIEW' => self::LARGE_PREVIEW,
            'PDF' => self::PDF,
            'VIDEO' => self::VIDEO,
            default => self::OTHER,
        };
    }

    public function getMaxSize(?Format $format = null): int
    {
        return match($format ?? $this)
        {
            self::THUMBNAIL => 250,
            self::PREVIEW,
            self::LARGE_PREVIEW => 3000,
            self::PDF, self::VIDEO => 320,
            default => 0,
        };
    }
}
