<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadResource;

final class DownloadHeaders
{

    private array $headers = [];

    private int $statusCode = 200;

    private ?string $overrideMime = null;

    private ContentDisposition $disposition = ContentDisposition::ATTACHMENT;
    
    private const string DEFAULT_CACHE_CONTROL = 'private, must-revalidate';

    public function __construct(
        private readonly DownloadResource $resource,
        private readonly ?DownloadETag $etag = null,
    ) {
    }

    public function setDisposition(ContentDisposition $disposition): self
    {
        $this->disposition = $disposition;
        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setOverrideMime(?string $mime): self
    {
        $this->overrideMime = $mime;
        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addHeader(string $name, int|string $value): self
    {
        $name = self::normalizeHeaderName($name);
        $this->headers[$name] = trim((string) $value);
        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addContentDispositionHeader(): self
    {
        $filename = $this->resource->getFilename();

        return $this->addHeader(
            'Content-Disposition',
            sprintf(
                "%s; filename=\"%s\"; filename*=UTF-8''%s",
                $this->disposition->value,
                self::sanitizeFilename($filename),
                rawurlencode($filename)
            )
        );
    }

    /**
     * @throws DownloadException
     */
    public function addContentLengthHeader(int $length): self
    {
        return $this->addHeader('Content-Length', $length);
    }

    /**
     * @throws DownloadException
     */
    public function addAcceptRangesHeader(): self
    {
        return $this->addHeader('Accept-Ranges', 'bytes');
    }

    /**
     * @throws DownloadException
     */
    public function addContentRangeHeader(string $value): self
    {
        return $this->addHeader('Content-Range', $value);
    }

    /**
     * @throws DownloadException
     */
    public function addConditionalHeaders(): self
    {
        return $this
            ->addLastModifiedHeader()
            ->addETagHeader();
    }

    public function build(): void
    {
        $this->applyDefaultHeaders();

        if ($this->statusCode !== 304) {
            $mime = $this->overrideMime ?? $this->resource->getMime();
            header('Content-Type: ' . $mime);
        }

        foreach ($this->headers as $name => $value) {
            header($name.': '.$value);
        }

        http_response_code($this->statusCode);
    }

    /**
     * @throws DownloadException
     */
    private function addLastModifiedHeader(): self
    {
        if ($this->resource->getLastModified() !== null) {
            return $this->addHeader(
                'Last-Modified',
                gmdate('D, d M Y H:i:s', $this->resource->getLastModified()) . ' GMT'
            );
        }
        return $this;
    }

    /**
     * @throws DownloadException
     */
    private function addETagHeader(): self
    {
        if ($this->etag !== null) {
            return $this->addHeader('ETag', $this->etag->getValue());
        }

        return $this;
    }

    private function applyDefaultHeaders(): void
    {
        if (!isset($this->headers['Cache-Control'])) {
            $this->headers['Cache-Control'] ??= self::DEFAULT_CACHE_CONTROL;
        }
    }

    /**
     * @throws DownloadException
     */
    private static function normalizeHeaderName(string $name): string
    {
        $name = trim($name);

        if (!preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            throw DownloadException::invalidHeaderName($name);
        }

        return ucwords($name, '-');
    }

    private static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);

        return addcslashes($filename, "\"\\");
    }
}
