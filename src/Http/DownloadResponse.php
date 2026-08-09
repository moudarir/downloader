<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeResolver;
use Moudarir\Downloader\Resources\DownloadResource;

final class DownloadResponse
{

    private int $statusCode = 200;

    private ?int $contentLength = null;

    private function __construct(
        private readonly DownloadHeaders  $headers,
        private readonly DownloadResource $resource,
        private readonly ?DownloadETag    $etag = null,
        private readonly ?DownloadRange   $range = null,
        private readonly bool             $isHead = false,
        private readonly bool             $isServerSide = false,
    )
    {
    }

    public static function precondition(
        int $statusCode,
        DownloadHeaders $headers,
        DownloadResource $resource,
        ?DownloadETag $etag = null,
        bool $isServerSide = false
    ): self
    {
        return new self(
            $headers,
            $resource,
            $etag,
            isServerSide: $isServerSide
        )->setStatusCode($statusCode);
    }

    /**
     * @throws DownloadException
     */
    public static function ok(
        DownloadHeaders $headers,
        DownloadResource $resource,
        DownloadRequest $request,
        ?DownloadETag $etag = null,
        bool $isPartial = false,
        bool $isServerSide = false,
    ): self
    {
        $headers->addContentDispositionHeader($resource->getFilename());
        $range = null;

        if ($isPartial === true) {
            $headers->addAcceptRangesHeader();

            $range = DownloadRangeResolver::create($resource, $request, $etag);

            if ($range === null) {
                $headers
                    ->addContentRangeHeader(sprintf('bytes */%d', $resource->getFilesize()));
                return new self(
                    $headers,
                    $resource,
                    $etag,
                    isHead: $request->isHead(),
                    isServerSide: $isServerSide
                )
                    ->setContentLength(0)
                    ->setStatusCode(416);
            }
        }

        return new self(
            $headers,
            $resource,
            $etag,
            $range,
            $request->isHead(),
            $isServerSide
        );
    }

    /**
     * @throws DownloadException
     */
    public function send(): void
    {
        if ($this->isServerSide === false) {
            @set_time_limit(0);
        }

        self::clearOutputBuffers();
        $this->headers
            ->addLastModifiedHeader($this->resource->getLastModified())
            ->addETagHeader($this->etag?->getValue())
            ->applyDefaultHeaders();

        $multipart = null;
        $contentLength = $this->resource->getFilesize();
        $contentStart = 0;
        $contentType = $this->resource->getMime();

        if ($this->range !== null && $this->range->isPartial()) {
            if ($this->range->isMultipart()) {
                $multipart = new DownloadMultipartResponse($this->resource, $this->range);
                $contentLength = $multipart->getContentLength();
                $contentType = $multipart->getContentType();
            } else {
                $item = $this->range->getFirstItem();
                $contentLength = $item->getLength();
                $contentStart = $item->getStart();

                $this->headers->addContentRangeHeader(
                    sprintf(
                        'bytes %d-%d/%d',
                        $contentStart,
                        $item->getEnd(),
                        $this->resource->getFilesize()
                    )
                );
            }

            $this->statusCode = 206;
        }

        $this->headers->addContentType($contentType);

        if (!isset($this->contentLength)) {
            $this->contentLength = $contentLength;
        }

        if ($this->statusCode !== 304) {
            $this->headers->addContentLengthHeader($this->contentLength);
        }

        foreach ($this->headers->all() as $name => $value) {
            header($name.': '.$value);
        }

        http_response_code($this->statusCode);

        if ($this->isHead === false && $this->isServerSide === false) {
            if ($multipart !== null) {
                $multipart->output();
                return;
            }

            $this->resource->output($contentLength, $contentStart);
        }
    }

    private function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    private function setContentLength(int $contentLength): self
    {
        $this->contentLength = $contentLength;
        return $this;
    }

    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) {
                break;
            }
        }
    }
}
