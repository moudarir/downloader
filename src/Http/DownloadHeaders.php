<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Helpers\FileHelper;

final class DownloadHeaders
{

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    private ContentDisposition $disposition = ContentDisposition::ATTACHMENT;

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->headers;
    }

    public function setDisposition(ContentDisposition $disposition): self
    {
        $this->disposition = $disposition;
        return $this;
    }

    /**
     * Add a validated HTTP header.
     *
     * @throws DownloadException
     */
    public function addHeader(string $name, int|string $value): self
    {
        $name = CommonHelper::validateHeaderName($name);
        $value = CommonHelper::validateHeaderValue((string) $value);

        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addContentDispositionHeader(string $filename): self
    {
        return $this->addHeader(
            'Content-Disposition',
            FileHelper::formatContentDisposition($filename, $this->disposition)
        );
    }

    /**
     * Add the Content-Length header.
     *
     * @throws DownloadException
     */
    public function addContentLengthHeader(int $length): self
    {
        return $this->addHeader('Content-Length', $length);
    }

    /**
     * Add the Accept-Ranges header.
     *
     * @throws DownloadException
     */
    public function addAcceptRangesHeader(): self
    {
        return $this->addHeader('Accept-Ranges', 'bytes');
    }

    /**
     * Add the Content-Range header.
     *
     * @throws DownloadException
     */
    public function addContentRangeHeader(string $value): self
    {
        return $this->addHeader('Content-Range', $value);
    }

    /**
     * Add the Content-Type header.
     *
     * @throws DownloadException
     */
    public function addContentTypeHeader(string $value): self
    {
        return $this->addHeader('Content-Type', $value);
    }

    /**
     * Add the Last-Modified header.
     *
     * @throws DownloadException
     */
    public function addLastModifiedHeader(?int $value = null): self
    {
        if ($value !== null) {
            return $this->addHeader('Last-Modified', gmdate('D, d M Y H:i:s', $value) . ' GMT');
        }

        return $this;
    }

    /**
     * Add the ETag header.
     *
     * @throws DownloadException
     */
    public function addETagHeader(?string $value = null): self
    {
        if ($value !== null) {
            return $this->addHeader('ETag', $value);
        }

        return $this;
    }

    /**
     * @throws DownloadException
     */
    public function addCacheControlHeader(string $value): self
    {
        return $this->addHeader('Cache-Control', $value);
    }

    /**
     * @throws DownloadException
     */
    public function addXSendfileHeader(string $filepath): self
    {
        return $this->addHeader('X-Sendfile', $filepath);
    }

    /**
     * @throws DownloadException
     */
    public function addXAccelRedirectHeader(string $internalUri): self
    {
        return $this->addHeader('X-Accel-Redirect', $internalUri);
    }

    /**
     * Disables FastCGI buffering for Nginx to allow real-time chunk streaming.
     *
     * @throws DownloadException
     */
    public function disableBuffering(): self
    {
        return $this->addHeader('X-Accel-Buffering', 'no');
    }

    /**
     * Apply default headers when they have not been explicitly defined.
     */
    public function applyDefaultHeaders(): void
    {
        if (!isset($this->headers['Cache-Control'])) {
            $this->headers['Cache-Control'] = DownloadConfig::DEFAULT_CACHE_CONTROL;
        }
    }
}
