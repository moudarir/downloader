<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\PreconditionStatus;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadPreconditions
{

    private function __construct(
        private DownloadRequest $request,
        private DownloadResource $resource,
        private ?DownloadETag $etag,
    ) {
    }

    public static function evaluate(
        DownloadRequest $request,
        DownloadResource $resource,
        ?DownloadETag $etag
    ): DownloadPreconditionResult
    {
        $instance = new self($request, $resource, $etag);

        if (($result = $instance->evaluateIfMatch()) !== null) {
            return $result;
        }

        if (($result = $instance->evaluateIfUnmodifiedSince()) !== null) {
            return $result;
        }

        if (($result = $instance->evaluateIfNoneMatch()) !== null) {
            return $result;
        }

        if (($result = $instance->evaluateIfModifiedSince()) !== null) {
            return $result;
        }

        return DownloadPreconditionResult::ok();
    }

    private function evaluateIfMatch(): ?DownloadPreconditionResult
    {
        if (($match = $this->request->getIfMatch()) === null) {
            return null;
        }

        if ($this->etag === null) {
            return DownloadPreconditionResult::preconditionFailed();
        }

        if ($match === '*') {
            return DownloadPreconditionResult::ok();
        }

        if ($this->etag->matches($match, false)) {
            return DownloadPreconditionResult::ok();
        }

        return DownloadPreconditionResult::preconditionFailed();
    }

    private function evaluateIfUnmodifiedSince(): ?DownloadPreconditionResult
    {
        if (($since = $this->request->getIfUnmodifiedSince()) === null) {
            return null;
        }

        if (($lastModified = $this->resource->getLastModified()) === null) {
            return null;
        }

        if ($lastModified <= $since) {
            return DownloadPreconditionResult::ok();
        }

        return DownloadPreconditionResult::preconditionFailed();
    }

    private function evaluateIfNoneMatch(): ?DownloadPreconditionResult
    {
        if (($noneMatch = $this->request->getIfNoneMatch()) === null) {
            return null;
        }

        if ($this->etag === null) {
            return null;
        }

        if (!$this->etag->matches($noneMatch)) {
            return DownloadPreconditionResult::ok();
        }

        return new DownloadPreconditionResult(
            $this->request->isGet() || $this->request->isHead()
                ? PreconditionStatus::NOT_MODIFIED
                : PreconditionStatus::PRECONDITION_FAILED
        );
    }

    private function evaluateIfModifiedSince(): ?DownloadPreconditionResult
    {
        if (($since = $this->request->getIfModifiedSince()) === null) {
            return null;
        }

        if (($lastModified = $this->resource->getLastModified()) === null) {
            return null;
        }

        if ($lastModified <= $since) {
            return DownloadPreconditionResult::notModified();
        }

        return DownloadPreconditionResult::ok();
    }
}
