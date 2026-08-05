<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\RequestMethod;
use Moudarir\Downloader\Helpers\CommonHelper;

final readonly class DownloadRequest
{
    private function __construct(
        private RequestMethod $method,
        private ?string $range,
        private ?string $ifRange,
        private ?string $ifMatch,
        private ?string $ifNoneMatch,
        private ?int $ifModifiedSince,
        private ?int $ifUnmodifiedSince,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD'
                ? RequestMethod::HEAD
                : RequestMethod::GET,
            CommonHelper::nullIfEmpty($_SERVER['HTTP_RANGE'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_RANGE'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_MATCH'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''),
            CommonHelper::httpDateToTimestamp($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''),
            CommonHelper::httpDateToTimestamp($_SERVER['HTTP_IF_UNMODIFIED_SINCE'] ?? ''),
        );
    }

    public function getMethod(): RequestMethod
    {
        return $this->method;
    }

    public function isGet(): bool
    {
        return $this->method === RequestMethod::GET;
    }

    public function isHead(): bool
    {
        return $this->method === RequestMethod::HEAD;
    }

    public function getRange(): ?string
    {
        return $this->range;
    }

    public function getIfRange(): ?string
    {
        return $this->ifRange;
    }

    public function getIfMatch(): ?string
    {
        return $this->ifMatch;
    }

    public function getIfNoneMatch(): ?string
    {
        return $this->ifNoneMatch;
    }

    public function getIfModifiedSince(): ?int
    {
        return $this->ifModifiedSince;
    }

    public function getIfUnmodifiedSince(): ?int
    {
        return $this->ifUnmodifiedSince;
    }
}
