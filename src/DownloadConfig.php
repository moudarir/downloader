<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

use Moudarir\Downloader\Exceptions\DownloadException;

final readonly class DownloadConfig
{

    public const int CHUNK_SIZE = 131_072; // Read buffer size in bytes. Default: 128 KB

    public const int MAX_RANGE_ITEMS = 10; // Protection against Denial of Service attacks (DoS)

    public const int BYTES_PER_SECOND = 0; // Maximum bandwidth limit in bytes/sec. Default: 0 (unlimited)

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
        'x-accel-buffering'   => 'X-Accel-Buffering',
        'x-accel-redirect'    => 'X-Accel-Redirect',
        'x-sendfile'          => 'X-Sendfile',
    ];

    /**
     * @throws DownloadException
     */
    public function __construct(
        private int $chunkSize = self::CHUNK_SIZE,
        private int $maxRangeItems = self::MAX_RANGE_ITEMS,
        private int $bytesPerSecond = self::BYTES_PER_SECOND,
    ) {
        if ($this->chunkSize < 1024) {
            throw DownloadException::invalidChunkSize();
        }

        if ($this->maxRangeItems <= 0) {
            throw DownloadException::invalidMaxRangeItems();
        }

        if ($this->bytesPerSecond < 0) {
            throw DownloadException::invalidLimitRate();
        }
    }

    /**
     * @throws DownloadException
     */
    public function withChunkSize(int $chunkSize): self
    {
        return new self($chunkSize, $this->maxRangeItems, $this->bytesPerSecond);
    }

    /**
     * @throws DownloadException
     */
    public function withMaxRangeItems(int $maxRangeItems): self
    {
        return new self($this->chunkSize, $maxRangeItems, $this->bytesPerSecond);
    }

    /**
     * @throws DownloadException
     */
    public function withLimitRate(int $bytesPerSecond): self
    {
        return new self($this->chunkSize, $this->maxRangeItems, $bytesPerSecond);
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getMaxRangeItems(): int
    {
        return $this->maxRangeItems;
    }

    public function getBytesPerSecond(): int
    {
        return $this->bytesPerSecond;
    }
}
