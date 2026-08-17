<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\MetadataHelper;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeResolver;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadResponse
{

    private function __construct(
        private DownloadHeaders            $headers,
        private DownloadResource           $resource,
        private DownloadRequest            $request,
        private ResponseAction             $responseAction,
        private DownloadETag               $etag,
        private StatusCode                 $statusCode,
        private int                        $contentLength,
        private ?string                    $contentType,
        private ?DownloadRange             $range = null,
        private ?DownloadMultipartResponse $multipart = null,
    )
    {
    }

    public static function precondition(
        StatusCode $statusCode,
        DownloadHeaders $headers,
        DownloadResource $resource,
        DownloadRequest $request,
        ResponseAction $responseAction,
        DownloadETag $etag,
    ): self
    {
        return new self(
            $headers,
            $resource,
            $request,
            $responseAction,
            $etag,
            $statusCode,
            0,
            null,
        );
    }

    /**
     * @throws DownloadException
     */
    public static function create(
        DownloadHeaders $headers,
        DownloadResource $resource,
        DownloadRequest $request,
        ResponseAction $responseAction,
        DownloadETag $etag,
    ): self
    {
        $statusCode = StatusCode::OK;
        $contentType = $resource->getMime();
        $contentLength = $resource->getFilesize();
        $range = null;
        $multipart = null;

        if ($responseAction === ResponseAction::PARTIAL) {
            $headers->addAcceptRangesHeader();

            $result = DownloadRangeResolver::create($resource, $request, $etag);

            if ($result->isUnsatisfiable()) {
                $headers->addContentRangeHeader(sprintf('bytes */%d', $resource->getFilesize()));

                return new self(
                    $headers,
                    $resource,
                    $request,
                    $responseAction,
                    $etag,
                    StatusCode::RANGE_NOT_SATISFIABLE,
                    0,
                    null,
                );
            }

            if ($result->isInvalid() === false) {
                $range = $result->getRange();

                if ($range->isPartial()) {
                    $statusCode = StatusCode::PARTIAL_CONTENT;

                    if ($range->isMultipart()) {
                        $multipart = new DownloadMultipartResponse($resource, $range);

                        $contentType = $multipart->getContentType();
                        $contentLength = $multipart->getContentLength();
                    } else {
                        $contentLength = $range->getFirstItem()->getLength();
                    }
                }
            }
        }

        return new self(
            $headers,
            $resource,
            $request,
            $responseAction,
            $etag,
            $statusCode,
            $contentLength,
            $contentType,
            $range,
            $multipart,
        );
    }

    /**
     * @throws DownloadException
     */
    public function send(): void
    {
        if ($this->responseAction->isServerSide() === false) {
            @set_time_limit(0);
        }

        self::clearOutputBuffers();

        $this->headers
            ->addLastModifiedHeader($this->resource->getLastModified())
            ->addETagHeader($this->etag->getValue())
            ->applyDefaultHeaders();

        match ($this->statusCode) {
            StatusCode::NOT_MODIFIED => $this->sendNotModified(),
            StatusCode::PRECONDITION_FAILED => $this->sendPreconditionFailed(),
            StatusCode::RANGE_NOT_SATISFIABLE => $this->sendRangeNotSatisfiable(),
            default => $this->sendRepresentation(),
        };
    }

    public function inline(): self
    {
        $this->headers->setDisposition(ContentDisposition::INLINE);
        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addCacheControl(string $value): self
    {
        $this->headers->addHeader('Cache-Control', $value);
        return $this;
    }

    public function metadata(): MetadataHelper
    {
        return MetadataHelper::create(
            $this->resource,
            $this->responseAction,
            $this->etag,
            $this->statusCode,
            $this->contentLength,
            $this->contentType,
            $this->range,
        );
    }

    /**
     * 304 responses never contain a message body.
     */
    private function sendNotModified(): void
    {
        $this->buildHeaders();
    }

    /**
     * 412 responses do not contain a representation in the current implementation.
     *
     * @throws DownloadException
     */
    private function sendPreconditionFailed(): void
    {
        $this->headers->addContentLengthHeader(0);

        $this->buildHeaders();
    }

    /**
     * 416 responses do not contain a message body in the current implementation.
     *
     * @throws DownloadException
     */
    private function sendRangeNotSatisfiable(): void
    {
        $this->headers->addContentLengthHeader(0);

        $this->buildHeaders();
    }

    /**
     * @throws DownloadException
     */
    private function sendRepresentation(): void
    {
        $this->headers
            ->addContentDispositionHeader($this->resource->getFilename())
            ->addContentType($this->contentType)
            ->addContentLengthHeader($this->contentLength);

        $contentStart = 0;

        if ($this->range !== null && $this->range->isPartial() && !$this->range->isMultipart()) {
            $item = $this->range->getFirstItem();

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

        $this->buildHeaders();

        if ($this->request->isHead() || $this->responseAction->isServerSide()) {
            return;
        }

        if ($this->multipart !== null) {
            $this->multipart->output();

            return;
        }

        $this->resource->output($this->contentLength, $contentStart);
    }

    private function buildHeaders(): void
    {
        foreach ($this->headers->all() as $name => $value) {
            header($name . ': ' . $value);
        }

        http_response_code($this->statusCode->value);
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
