<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

use DateTimeImmutable;
use DateTimeZone;

final class HttpDateHelper
{

    /**
     * RFC 9110 - HTTP-date formats
     *
     * - IMF-fixdate
     * - RFC 850
     * - ANSI C's asctime()
     */
    private const array HTTP_DATE_FORMATS = [
        'D, d M Y H:i:s \\G\\M\\T',
        'l, d-M-y H:i:s \\G\\M\\T',
        'D M j H:i:s Y',
    ];

    private static ?DateTimeZone $httpTimezone = null;

    public static function toTimestamp(string $date): ?int
    {
        if (($date = CommonHelper::nullIfEmpty($date)) === null) {
            return null;
        }

        foreach (self::HTTP_DATE_FORMATS as $format) {
            $datetime = self::parse($date, $format);

            if ($datetime !== null) {
                return $datetime->getTimestamp();
            }
        }

        return null;
    }

    private static function parse(string $date, string $format): ?DateTimeImmutable
    {
        self::$httpTimezone ??= new DateTimeZone('GMT');
        $datetime = DateTimeImmutable::createFromFormat($format, $date, self::$httpTimezone);

        if ($datetime === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) {
            return null;
        }

        // Ensure the parsed date round-trips exactly to the original string.
        if ($datetime->format($format) !== $date) {
            return null;
        }

        return $datetime;
    }
}
