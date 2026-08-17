<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum StatusCode: int
{
    case OK = 200;
    case PARTIAL_CONTENT = 206;
    case NOT_MODIFIED = 304;
    case PRECONDITION_FAILED = 412;
    case RANGE_NOT_SATISFIABLE = 416;
}
