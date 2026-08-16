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
    ];

    private const array MONTHS = [
        'Jan' => 1,
        'Feb' => 2,
        'Mar' => 3,
        'Apr' => 4,
        'May' => 5,
        'Jun' => 6,
        'Jul' => 7,
        'Aug' => 8,
        'Sep' => 9,
        'Oct' => 10,
        'Nov' => 11,
        'Dec' => 12,
    ];

    private static ?DateTimeZone $httpTimezone = null;

    public static function toTimestamp(string $date): ?int
    {
        if (($date = CommonHelper::nullIfEmpty($date)) === null) {
            return null;
        }

        if (($datetime = self::parse($date, self::HTTP_DATE_FORMATS[0])) !== null) {
            return $datetime->getTimestamp();
        }

        if (($datetime = self::parseRfc850($date)) !== null) {
            return $datetime->getTimestamp();
        }

        if (($datetime = self::parseAsctime($date)) !== null) {
            return $datetime->getTimestamp();
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

    private static function parseRfc850(string $date): ?DateTimeImmutable
    {
        if (preg_match(
                '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), ' .
                '(0[1-9]|[12]\d|3[01])-'.
                '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-'.
                '(\d{2}) ' .
                '(\d{2}):(\d{2}):(\d{2}) GMT$/',
                $date,
                $matches
            ) !== 1) {
            return null;
        }

        $dayName = $matches[1];
        $day = (int) $matches[2];
        $month = self::MONTHS[$matches[3]];
        $year = (int) $matches[4];
        $hour = (int) $matches[5];
        $minute = (int) $matches[6];
        $second = (int) $matches[7];

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        $now = new DateTimeImmutable('now', self::$httpTimezone ??= new DateTimeZone('GMT'));

        $currentYear = (int) $now->format('Y');
        $currentCentury = intdiv($currentYear, 100) * 100;
        $candidateYear = $currentCentury + $year;

        $datetime = self::createDate(
            $candidateYear,
            $month,
            $day,
            $hour,
            $minute,
            $second
        );

        if ($datetime === null) {
            return null;
        }

        /*
         * RFC 9110:
         * If the timestamp appears to be more than 50 years
         * in the future, use the most recent year in the past
         * having the same last two digits.
         */
        if ($datetime > $now->modify('+50 years')) {
            $datetime = self::createDate(
                $candidateYear - 100,
                $month,
                $day,
                $hour,
                $minute,
                $second
            );

            if ($datetime === null) {
                return null;
            }
        }

        return $datetime->format('l') === $dayName
            ? $datetime
            : null;
    }

    private static function parseAsctime(string $date): ?DateTimeImmutable
    {
        if (preg_match(
                '/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun) ' .
                '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) ' .
                '( \d|\d{2}) ' .
                '(\d{2}):(\d{2}):(\d{2}) ' .
                '(\d{4})$/',
                $date,
                $matches
            ) !== 1) {
            return null;
        }

        $dayName = $matches[1];
        $month = self::MONTHS[$matches[2]];
        $day = (int) trim($matches[3]);
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = (int) $matches[6];
        $year = (int) $matches[7];

        $datetime = self::createDate(
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second
        );

        if ($datetime === null) {
            return null;
        }

        return $datetime->format('D') === $dayName
            ? $datetime
            : null;
    }

    private static function createDate(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second
    ): ?DateTimeImmutable
    {
        self::$httpTimezone ??= new DateTimeZone('GMT');

        $datetime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second
            ),
            self::$httpTimezone
        );

        if ($datetime === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) {
            return null;
        }

        return $datetime;
    }
}
