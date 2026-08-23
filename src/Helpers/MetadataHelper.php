<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class MetadataHelper
{

    private function __construct(
        private DownloadResource $resource,
        private ResponseAction   $responseAction,
        private DownloadETag     $etag,
        private StatusCode       $statusCode,
        private int              $contentLength,
        private ?string          $contentType,
        private ?DownloadRange   $range = null,
    )
    {
    }

    public static function create(
        DownloadResource $resource,
        ResponseAction   $responseAction,
        DownloadETag     $etag,
        StatusCode       $statusCode,
        int              $contentLength,
        ?string          $contentType = null,
        ?DownloadRange   $range = null
    ): self
    {
        return new self(
            $resource,
            $responseAction,
            $etag,
            $statusCode,
            $contentLength,
            $contentType,
            $range,
        );
    }

    public function filepath(): ?string
    {
        return $this->resource->getFilepath();
    }

    public function filename(): string
    {
        return $this->resource->getFilename();
    }

    public function filesize(): int
    {
        return $this->resource->getFilesize();
    }

    public function mimeType(): string
    {
        return $this->resource->getMime();
    }

    public function lastModified(): ?int
    {
        return $this->resource->getLastModified();
    }

    public function statusCode(): StatusCode
    {
        return $this->statusCode;
    }

    public function contentLength(): int
    {
        return $this->contentLength;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    public function responseAction(): ResponseAction
    {
        return $this->responseAction;
    }

    public function etagValue(): string
    {
        return $this->etag->getValue();
    }

    public function etagOpaqueValue(): string
    {
        return $this->etag->getOpaqueValue();
    }

    public function etagIsWeak(): bool
    {
        return $this->etag->isWeak();
    }

    /**
     * @return list<DownloadRangeItem>|null
     */
    public function rangeItems(): ?array
    {
        return $this->range?->getItems();
    }

    public function hasRange(): bool
    {
        return $this->range !== null;
    }

    public function rangeIsPartial(): bool
    {
        return $this->range?->isPartial() ?? false;
    }

    public function rangeIsMultipart(): bool
    {
        return $this->range?->isMultipart() ?? false;
    }
}
