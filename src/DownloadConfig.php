<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

final class DownloadConfig
{

    public const bool ENABLE_ETAG = true;

    public const string DEFAULT_MIME = 'application/octet-stream';

    public const int CHUNK_SIZE = 128 * 1024;

    public const int MAX_RANGE_ITEMS = 10; // Protection contre les attaques par déni de service (DoS)
}
