<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

final class DownloadConfig
{

    public const string DEFAULT_MIME = 'application/octet-stream';

    public const int CHUNK_SIZE = 128 * 1024;

    public const int MAX_RANGE_ITEMS = 10; // Protection contre les attaques par déni de service (DoS)

    public const string DEFAULT_CACHE_CONTROL = 'private, must-revalidate';

    /**
     * Headers explicitly supported by the library.
     *
     * @var array<string, string>
     */
    public const array VALID_HEADERS = [
        'accept-ranges'       => 'Accept-Ranges',
        'cache-control'       => 'Cache-Control',
        'content-disposition' => 'Content-Disposition',
        'content-length'      => 'Content-Length',
        'content-range'       => 'Content-Range',
        'content-type'        => 'Content-Type',
        'etag'                => 'ETag',
        'last-modified'       => 'Last-Modified',
        'x-accel-redirect'    => 'X-Accel-Redirect',
        'x-sendfile'          => 'X-Sendfile',
    ];
}
