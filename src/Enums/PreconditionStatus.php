<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum PreconditionStatus: int
{
    case OK = 200;
    case NOT_MODIFIED = 304;
    case PRECONDITION_FAILED = 412;
}
