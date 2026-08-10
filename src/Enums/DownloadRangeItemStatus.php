<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum DownloadRangeItemStatus
{
    case INVALID;
    case UNSATISFIABLE;
}
