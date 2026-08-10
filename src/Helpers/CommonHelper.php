<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Exceptions\DownloadException;

final class CommonHelper
{

    public static function nullIfEmpty(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $data = trim($data);
        return $data === '' ? null : $data;
    }

    /**
     * @throws DownloadException
     */
    public static function validateHeaderName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || !preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            throw DownloadException::invalidHeaderName($name);
        }

        foreach (DownloadConfig::VALID_HEADERS as $header) {
            if (strcasecmp($name, $header) === 0) {
                return $header;
            }
        }

        throw DownloadException::invalidHeaderName($name);
    }

    /**
     * Validate an HTTP header value.
     *
     * CR and LF characters are forbidden to prevent HTTP header injection.
     *
     * @throws DownloadException
     */
    public static function validateHeaderValue(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw DownloadException::invalidHeaderValue($value);
        }

        return $value;
    }
}
