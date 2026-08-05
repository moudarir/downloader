<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum RequestMethod: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
}
