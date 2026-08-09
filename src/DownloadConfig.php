<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

final class DownloadConfig
{

    public const bool ENABLE_ETAG = true;

    public const string DEFAULT_MIME = 'application/octet-stream';

    public const int CHUNK_SIZE = 128 * 1024;

    public const int MAX_RANGE_ITEMS = 10; // Protection contre les attaques par déni de service (DoS)

    public const string DEFAULT_CACHE_CONTROL = 'private, must-revalidate';

    /**
     * Headers explicitly supported by the library.
     *
     * @var list<string>
     */
    public const array VALID_HEADERS = [
        'Accept-Ranges',
        'Cache-Control',
        'Content-Disposition',
        'Content-Length',
        'Content-Range',
        'Content-Type',
        'ETag',
        'Last-Modified',
        'X-Accel-Redirect',
        'X-Sendfile',
    ];
}
