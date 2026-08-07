<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

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
}
