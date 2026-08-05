<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\ETag\DownloadETagResolver;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadHeaders;
use Moudarir\Downloader\Http\DownloadMultipartResponse;
use Moudarir\Downloader\Http\DownloadPreconditions;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Range\DownloadRangeResolver;
use Moudarir\Downloader\Resources\DownloadData;
use Moudarir\Downloader\Resources\DownloadFile;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class Download
{

    private DownloadHeaders $headers;

    private ?DownloadETag $etag;

    private DownloadRequest $request;

    /**
     * @throws DownloadException
     */
    private function __construct(private DownloadResource $resource, ?ETagStrategy $strategy = null)
    {
        $this->request = DownloadRequest::fromGlobals();

        $this->etag = DownloadConfig::ENABLE_ETAG
            ? DownloadETag::create(
                $this->resource,
                DownloadETagResolver::resolve($this->resource, $strategy)
            )
            : null;

        $this->headers = new DownloadHeaders($this->resource, $this->etag);
    }

    /**
     * @throws DownloadException
     */
    public static function fromFile(string $filepath, ?string $filename = null, bool $detectMime = false, ?ETagStrategy $strategy = null): self
    {
        return new self(DownloadFile::create($filepath, $filename, $detectMime), $strategy);
    }

    /**
     * @throws DownloadException
     */
    public static function fromData(string $data, string $filename, ?string $mime = null, ?ETagStrategy $strategy = null): self
    {
        return new self(DownloadData::create($data, $filename, $mime), $strategy);
    }

    /**
     * @throws DownloadException
     */
    public function stream(): void
    {
        @set_time_limit(0);
        $this->initializeResponse();

        $this->headers
            ->addContentLengthHeader($this->resource->getFilesize())
            ->build();

        if ($this->request->isHead()) {
            self::finish();
        }

        $this->resource->output($this->resource->getFilesize());
        self::finish();
    }

    /**
     * @throws DownloadException
     */
    public function streamPartial(): void
    {
        @set_time_limit(0);
        $this->initializeResponse();

        $this->headers->addAcceptRangesHeader();

        $filesize = $this->resource->getFilesize();
        $range = new DownloadRangeResolver($this->request, $this->resource, $this->etag)
            ->resolve();

        if ($range === null) {
            $this->headers
                ->setStatusCode(416)
                ->addContentLengthHeader(0)
                ->addContentRangeHeader(sprintf('bytes */%d', $filesize))
                ->build();
            self::finish();
        }

        $multipart = null;

        if ($range->isPartial()) {
            if ($range->isMultipart()) {
                $multipart = new DownloadMultipartResponse($this->resource, $range);
                $contentLength = $multipart->getContentLength();

                $this->headers->setOverrideMime($multipart->getContentType());
            } else {
                $item = $range->getFirstItem();
                $contentLength = $item->getLength();

                $this->headers
                    ->addContentRangeHeader(
                        sprintf('bytes %d-%d/%d', $item->getStart(), $item->getEnd(), $filesize)
                    );
            }

            $this->headers
                ->setStatusCode(206)
                ->addContentLengthHeader($contentLength);
        } else {
            $this->headers->addContentLengthHeader($filesize);
        }

        $this->headers->build();

        if ($this->request->isHead()) {
            self::finish();
        }

        if ($multipart !== null) {
            $multipart->output();
            self::finish();
        }

        $item = $range->getFirstItem();

        $this->resource->output($item->getLength(), $item->getStart());

        flush();
        self::finish();
    }

    /**
     * @throws DownloadException
     */
    public function streamXSendFile(): void
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Sendfile');
        }

        $this->initializeResponse();

        if ($this->request->isHead()) {
            $this->headers->build();
            self::finish();
        }

        $this->headers
            ->addHeader('X-Sendfile', $this->resource->getFilepath())
            ->build();
        self::finish();
    }

    /**
     * @throws DownloadException
     */
    public function streamXAccelRedirect(string $internalUri): void
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Accel-Redirect');
        }

        $this->initializeResponse();

        if ($this->request->isHead()) {
            $this->headers->build();
            self::finish();
        }

        $this->headers
            ->addHeader('X-Accel-Redirect', $internalUri)
            ->build();
        self::finish();
    }

    public function inline(): self
    {
        $this->headers->setDisposition(ContentDisposition::INLINE);
        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addHeader(string $name, int|string $value): self
    {
        $this->headers->addHeader($name, $value);
        return $this;
    }

    /**
     * @throws DownloadException
     */
    private function initializeResponse(): void
    {
        self::clearOutputBuffers();
        $this->headers->addConditionalHeaders();
        $this->checkPreconditions();
        $this->headers->addContentDispositionHeader();
    }

    private function checkPreconditions(): void
    {
        $result = DownloadPreconditions::evaluate($this->request, $this->resource, $this->etag);

        if ($result->isOk()) {
            return;
        }

        $this->headers
            ->setStatusCode($result->getStatusCode())
            ->build();
        self::finish();
    }

    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) {
                break;
            }
        }
    }

    private static function finish(): never
    {
        exit;
    }
}
