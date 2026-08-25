<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\DownloadConfig;
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
        private DownloadConfig             $config,
        private ?DownloadRange             $range = null,
        private ?DownloadMultipartResponse $multipart = null,
    )
    {
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
        DownloadConfig $config = new DownloadConfig(),
    ): self
    {
        $result = DownloadPreconditions::evaluate(
            $request,
            $resource,
            $etag
        );

        if ($result->isOk() === false) {
            return new self(
                $headers,
                $resource,
                $request,
                $responseAction,
                $etag,
                $result->getStatusCode(),
                0,
                null,
                $config,
            );
        }

        $statusCode = StatusCode::OK;
        $contentType = $resource->getMime();
        $contentLength = $resource->getFilesize();
        $range = null;
        $multipart = null;

        if ($responseAction === ResponseAction::PARTIAL) {
            $headers->addAcceptRangesHeader();

            $result = DownloadRangeResolver::create($resource, $request, $etag, $config);

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
                    $config
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
            $config,
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

        // Disable PHP's dynamic zlib compression if enabled
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', 'Off');
        }

        // clear output buffers
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) {
                break;
            }
        }

        $this->addHeaders();

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
        $this->headers->addCacheControlHeader($value);
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
            ->addContentTypeHeader($this->contentType)
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
            $this->multipart->output($this->config);

            return;
        }

        $this->resource->output($this->config, $this->contentLength, $contentStart);
    }

    private function buildHeaders(): void
    {
        foreach ($this->headers->all() as $name => $value) {
            header($name . ': ' . $value);
        }

        http_response_code($this->statusCode->value);
    }

    /**
     * @throws DownloadException
     */
    private function addHeaders(): void
    {
        $this->headers
            ->addLastModifiedHeader($this->resource->getLastModified())
            ->addETagHeader($this->etag->getValue())
            ->applyDefaultHeaders();

        if ($this->responseAction->isServerSide() === false) {
            $this->headers->disableBuffering();
        }

        if ($this->responseAction === ResponseAction::X_SEND_FILE) {
            $this->headers->addXSendfileHeader($this->resource->getFilepath());
        }

        if ($this->responseAction === ResponseAction::X_ACCEL_REDIRECT) {
            $this->headers->addXAccelRedirectHeader($this->resource->getInternalUri());
        }
    }
}
