<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum ResponseAction: string
{
    case DEFAULT = 'default';
    case PARTIAL = 'partial';
    case X_SEND_FILE = 'x-send-file';
    case X_ACCEL_REDIRECT = 'x-accel-redirect';

    public function isServerSide(): bool
    {
        return match ($this) {
            self::X_SEND_FILE,
            self::X_ACCEL_REDIRECT => true,
            default => false,
        };
    }
}
