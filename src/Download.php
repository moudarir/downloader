<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadHeaders;
use Moudarir\Downloader\Http\DownloadPreconditions;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Http\DownloadResponse;
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
        $this->request = DownloadRequest::create();

        $this->etag = DownloadConfig::ENABLE_ETAG
            ? DownloadETag::create($this->resource, $strategy)
            : null;

        $this->headers = new DownloadHeaders();
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
    public function stream(): DownloadResponse
    {
        $result = DownloadPreconditions::evaluate($this->request, $this->resource, $this->etag);

        if ($result->isOk() === false) {
            return  DownloadResponse::precondition(
                $result->getStatusCode(),
                $this->headers,
                $this->resource,
                $this->etag
            );
        }

        return  DownloadResponse::ok(
            $this->headers,
            $this->resource,
            $this->request,
            $this->etag,
        );
    }

    /**
     * @throws DownloadException
     */
    public function streamPartial(): DownloadResponse
    {
        $result = DownloadPreconditions::evaluate($this->request, $this->resource, $this->etag);

        if ($result->isOk() === false) {
            return  DownloadResponse::precondition(
                $result->getStatusCode(),
                $this->headers,
                $this->resource,
                $this->etag
            );
        }

        return  DownloadResponse::ok(
            $this->headers,
            $this->resource,
            $this->request,
            $this->etag,
            isPartial: true,
        );
    }

    /**
     * @throws DownloadException
     */
    public function streamXSendFile(): DownloadResponse
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Sendfile');
        }

        $result = DownloadPreconditions::evaluate($this->request, $this->resource, $this->etag);

        if ($result->isOk() === false) {
            return  DownloadResponse::precondition(
                $result->getStatusCode(),
                $this->headers,
                $this->resource,
                $this->etag,
                isServerSide: true
            );
        }

        $this->headers->addHeader('X-Sendfile', $this->resource->getFilepath());

        return  DownloadResponse::ok(
            $this->headers,
            $this->resource,
            $this->request,
            $this->etag,
            isServerSide: true
        );
    }

    /**
     * @throws DownloadException
     */
    public function streamXAccelRedirect(string $internalUri): DownloadResponse
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Accel-Redirect');
        }

        $result = DownloadPreconditions::evaluate($this->request, $this->resource, $this->etag);

        if ($result->isOk() === false) {
            return  DownloadResponse::precondition(
                $result->getStatusCode(),
                $this->headers,
                $this->resource,
                $this->etag,
                isServerSide: true
            );
        }

        $this->headers->addHeader('X-Accel-Redirect', $internalUri);

        return  DownloadResponse::ok(
            $this->headers,
            $this->resource,
            $this->request,
            $this->etag,
            isServerSide: true
        );
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
}
