<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
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
        $length = 0;

        foreach ($this->range->getItems() as $item) {
            $length += strlen($this->getPartHeader($item))
                + $item->getLength()
                + 2;  // CRLF after the part body
        }

        return $length + strlen($this->getClosingBoundary());
    }

    public function output(): void
    {
        foreach ($this->range->getItems() as $item) {
            echo $this->getPartHeader($item);
            flush();

            $this->resource->output($item->getLength(), $item->getStart());

            echo "\r\n";
            flush();
        }

        echo $this->getClosingBoundary();
        flush();
    }

    private function getPartHeader(DownloadRangeItem $item): string
    {
        return sprintf(
            "--%s\r\nContent-Type: %s\r\nContent-Range: bytes %d-%d/%d\r\n\r\n",
            $this->range->getBoundary(),
            $this->resource->getMime(),
            $item->getStart(),
            $item->getEnd(),
            $this->resource->getFilesize()
        );
    }

    private function getClosingBoundary(): string
    {
        return sprintf("--%s--\r\n", $this->range->getBoundary());
    }
}
