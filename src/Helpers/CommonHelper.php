<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

final class CommonHelper
{

    public static function httpDateToTimestamp(string $date): ?int
    {
        if (($datetime = self::nullIfEmpty($date)) === null) {
            return null;
        }

        try {
            return new \DateTime($datetime)->getTimestamp();
        } catch (\DateMalformedStringException $exception) {
            return null;
        }
    }

    public static function nullIfEmpty(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $data = trim($data);
        return $data === '' ? null : $data;
    }
}
