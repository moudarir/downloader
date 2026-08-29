<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

use Moudarir\Downloader\Enums\ContentDisposition;

final class FileHelper
{

    /**
     * The filename parameter contains an ASCII fallback for clients
     * that do not support RFC 5987/6266 extended parameters.
     *
     * The filename* parameter contains the original UTF-8 filename.
     */
    public static function formatContentDisposition(
        string $filename,
        ContentDisposition $disposition = ContentDisposition::ATTACHMENT
    ): string
    {
        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition->value,
            self::sanitizeFilename($filename),
            rawurlencode($filename)
        );
    }

    /**
     * Build a safe ASCII fallback for the Content-Disposition filename.
     *
     * Control characters are removed first. The /u modifier is deliberately
     * avoided so that invalid UTF-8 byte sequences do not cause preg_replace()
     * to fail.
     */
    private static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?? '';
        $filename = trim($filename);
        $pathInfo = pathinfo($filename);

        $basename = CommonHelper::removeAccents($pathInfo['filename'] ?? '');
        $basename = preg_replace('/[^\x20-\x7E]/', '', $basename) ?? '';
        $basename = trim($basename);

        if ($basename === '') {
            $basename = 'download';
        }

        $result = $basename;

        if (($extension = $pathInfo['extension'] ?? '') !== '') {
            $extension = CommonHelper::removeAccents($extension);
            $extension = preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '';

            if ($extension !== '') {
                $result .= '.' . $extension;
            }
        }

        return addcslashes($result, "\"\\");
    }
}
