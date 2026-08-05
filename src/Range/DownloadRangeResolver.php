<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadRangeResolver
{

    public function __construct(
        private DownloadRequest $request,
        private DownloadResource $resource,
        private ?DownloadETag $etag,
    ) {
    }

    public function resolve(): ?DownloadRange
    {
        if (!$this->shouldProcessRange()) {
            return DownloadRange::full($this->resource->getFilesize());
        }

        return DownloadRangeParser::parse($this->request->getRange(), $this->resource->getFilesize());
    }

    private function shouldProcessRange(): bool
    {
        if ($this->request->getRange() === null) {
            return false;
        }

        if (($ifRange = $this->request->getIfRange()) === null) {
            return true;
        }

        /*
         * RFC 9110
         * If-Range: "<etag>"
         */

        if (str_starts_with($ifRange, '"') || str_starts_with($ifRange, 'W/"')) {
            return $this->etag !== null
                && $this->etag->matches($ifRange, false);
        }

        /*
         * If-Range: HTTP-date
         */

        if (($lastModified = $this->resource->getLastModified()) === null) {
            return false;
        }

        if (($timestamp = CommonHelper::httpDateToTimestamp($ifRange)) === null) {
            return false;
        }

        return $lastModified <= $timestamp;
    }
}
