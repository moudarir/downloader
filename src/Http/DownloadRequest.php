<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\RequestMethod;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Helpers\HttpDateHelper;

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

    /**
     * @throws DownloadException
     */
    public static function create(): self
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($requestMethod === 'GET') {
            $method = RequestMethod::GET;
        } elseif ($requestMethod === 'HEAD') {
            $method = RequestMethod::HEAD;
        } else {
            throw DownloadException::unsupportedRequestMethod($requestMethod);
        }

        return new self(
            $method,
            CommonHelper::nullIfEmpty($_SERVER['HTTP_RANGE'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_RANGE'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_MATCH'] ?? ''),
            CommonHelper::nullIfEmpty($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''),
            HttpDateHelper::toTimestamp($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''),
            HttpDateHelper::toTimestamp($_SERVER['HTTP_IF_UNMODIFIED_SINCE'] ?? ''),
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

    public function isSafeMethod(): bool
    {
        return $this->isGet() || $this->isHead();
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
