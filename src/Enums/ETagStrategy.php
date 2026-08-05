<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Enums;

enum ETagStrategy: string
{
    case MTIME = 'mtime';
    case INODE = 'inode';
    case MD5 = 'md5';
    case SHA256 = 'sha256';
    case SHA512 = 'sha512';
}
