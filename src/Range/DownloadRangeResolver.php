<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\HttpDateHelper;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadRangeResolver
{

    public function __construct(
        private DownloadRequest $request,
        private DownloadETag $etag,
        private ?int $lastModified,
    ) {
    }

    /**
     * @throws DownloadException
     */
    public static function create(DownloadResource $resource, DownloadRequest $request, DownloadETag $etag): DownloadRangeResult
    {
        $resolver = new self($request, $etag, $resource->getLastModified());

        if (!$resolver->shouldProcessRange()) {
            return DownloadRangeResult::valid(DownloadRange::full($resource->getFilesize()));
        }

        return DownloadRangeParser::parse($request->getRange(), $resource->getFilesize());
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
            return $this->etag->matches($ifRange, false);
        }

        /*
         * If-Range: HTTP-date
         */

        if ($this->lastModified === null) {
            return false;
        }

        if (($timestamp = HttpDateHelper::toTimestamp($ifRange)) === null) {
            return false;
        }

        return $this->lastModified === $timestamp;
    }
}
