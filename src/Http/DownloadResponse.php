<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ResponseAction;
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
        private readonly DownloadRequest  $request,
        private readonly ResponseAction   $responseAction,
        private readonly DownloadETag     $etag,
        private readonly ?DownloadRange   $range = null,
    )
    {
    }

    public static function precondition(
        int $statusCode,
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
        )->setStatusCode($statusCode);
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
        $range = null;

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
                )
                    ->setContentLength(0)
                    ->setStatusCode(416);
            }

            if ($result->isInvalid() === false) {
                $range = $result->getRange();
            }
        }

        return new self(
            $headers,
            $resource,
            $request,
            $responseAction,
            $etag,
            $range,
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
            304 => $this->sendNotModified(),
            412 => $this->sendPreconditionFailed(),
            416 => $this->sendRangeNotSatisfiable(),
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
     * 416 has nobody in the current implementation.
     *
     * @throws DownloadException
     */
    private function sendRangeNotSatisfiable(): void
    {
        $this->headers->addContentLengthHeader($this->contentLength ?? 0);

        $this->buildHeaders();
    }

    /**
     * @throws DownloadException
     */
    private function sendRepresentation(): void
    {
        $this->headers->addContentDispositionHeader($this->resource->getFilename());

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

        $this->contentLength ??= $contentLength;

        $this->headers->addContentLengthHeader($this->contentLength);

        $this->buildHeaders();

        if ($this->request->isHead() || $this->responseAction->isServerSide()) {
            return;
        }

        if ($multipart !== null) {
            $multipart->output();

            return;
        }

        $this->resource->output($contentLength, $contentStart);
    }

    private function buildHeaders(): void
    {
        foreach ($this->headers->all() as $name => $value) {
            header($name . ': ' . $value);
        }

        http_response_code($this->statusCode);
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
