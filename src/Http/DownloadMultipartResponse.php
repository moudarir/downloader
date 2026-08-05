<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadMultipartResponse
{

    public function __construct(private DownloadResource $resource, private DownloadRange $range)
    {
    }

    public function getContentType(): string
    {
        return sprintf('multipart/byteranges; boundary=%s', $this->range->getBoundary());
    }

    public function getContentLength(): int
    {
        $filesize = $this->resource->getFilesize();
        $mime = $this->resource->getMime();
        $boundary = $this->range->getBoundary();
        $length = 0;

        foreach ($this->range->getItems() as $item) {
            $header = sprintf(
                "--%s\r\nContent-Type: %s\r\nContent-Range: bytes %d-%d/%d\r\n\r\n",
                $boundary,
                $mime,
                $item->getStart(),
                $item->getEnd(),
                $filesize
            );

            $length += strlen($header) + $item->getLength() + 2;
        }

        $length += strlen(sprintf("--%s--\r\n", $boundary));
        return $length;
    }

    public function output(): void
    {
        $boundary = $this->range->getBoundary();
        $mime = $this->resource->getMime();
        $filesize = $this->resource->getFilesize();

        foreach ($this->range->getItems() as $item) {
            echo sprintf(
                "--%s\r\nContent-Type: %s\r\nContent-Range: bytes %d-%d/%d\r\n\r\n",
                $boundary,
                $mime,
                $item->getStart(),
                $item->getEnd(),
                $filesize
            );
            flush();

            $this->resource->output($item->getLength(), $item->getStart());

            echo "\r\n";
            flush();
        }

        echo sprintf("--%s--\r\n", $boundary);
        flush();
    }
}
