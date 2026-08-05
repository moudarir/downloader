<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum ContentDisposition: string
{
    case ATTACHMENT = 'attachment';
    case INLINE = 'inline';
}
