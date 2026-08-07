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

        /*
         * RFC 9110 §13.2.2 defines a strict precedence order for request
         * preconditions.
         *
         * Each evaluation method returns:
         *
         *   - null
         *       The corresponding request header is absent, allowing evaluation
         *       to continue with the next precondition.
         *
         *   - DownloadPreconditionResult
         *       The request header is present and evaluation is complete.
         *       The returned status (200, 304 or 412) is final.
         */

        // RFC 9110: If-Match takes precedence over If-Unmodified-Since.
        if (($result = $instance->evaluateIfMatch()) !== null) {
            return $result;
        }

        if (($result = $instance->evaluateIfUnmodifiedSince()) !== null) {
            return $result;
        }

        // RFC 9110: If-None-Match takes precedence over If-Modified-Since.
        if (($result = $instance->evaluateIfNoneMatch()) !== null) {
            return $result;
        }

        if (($result = $instance->evaluateIfModifiedSince()) !== null) {
            return $result;
        }

        return DownloadPreconditionResult::proceed();
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
            return DownloadPreconditionResult::proceed();
        }

        return $this->etag->matches($match, false)
            ? DownloadPreconditionResult::proceed()
            : DownloadPreconditionResult::preconditionFailed();
    }

    private function evaluateIfUnmodifiedSince(): ?DownloadPreconditionResult
    {
        if (($since = $this->request->getIfUnmodifiedSince()) === null) {
            return null;
        }

        if (($lastModified = $this->resource->getLastModified()) === null) {
            return null;
        }

        return $lastModified <= $since
            ? DownloadPreconditionResult::proceed()
            : DownloadPreconditionResult::preconditionFailed();
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
            return DownloadPreconditionResult::proceed();
        }

        return new DownloadPreconditionResult(
            $this->request->isSafeMethod()
                ? PreconditionStatus::NOT_MODIFIED
                : PreconditionStatus::PRECONDITION_FAILED
        );
    }

    private function evaluateIfModifiedSince(): ?DownloadPreconditionResult
    {
        /*
         * RFC 9110 §13.1.3:
         * If-Modified-Since is only applicable to GET and HEAD requests.
         *
         * DownloadRequest currently only supports GET and HEAD, but this
         * explicit check documents the RFC requirement and prevents future
         * regressions if additional HTTP methods are introduced.
         */
        if ($this->request->isSafeMethod()) {
            return null;
        }

        if (($since = $this->request->getIfModifiedSince()) === null) {
            return null;
        }

        if (($lastModified = $this->resource->getLastModified()) === null) {
            return null;
        }

        return $lastModified <= $since
            ? DownloadPreconditionResult::notModified()
            : DownloadPreconditionResult::proceed();
    }
}