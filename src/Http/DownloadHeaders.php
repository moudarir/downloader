<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Exceptions\DownloadException;

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
        $name = self::validateHeaderName($name);
        $value = self::validateHeaderValue((string) $value);

        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add the Content-Disposition header.
     *
     * The filename parameter contains an ASCII fallback for clients
     * that do not support RFC 5987/6266 extended parameters.
     *
     * The filename* parameter contains the original UTF-8 filename.
     *
     * @throws DownloadException
     */
    public function addContentDispositionHeader(string $filename): self
    {
        return $this->addHeader(
            'Content-Disposition',
            sprintf(
                '%s; filename="%s"; filename*=UTF-8\'\'%s',
                $this->disposition->value,
                self::sanitizeFilename($filename),
                rawurlencode($filename)
            )
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
    public function addContentType(string $value): self
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
     * Apply default headers when they have not been explicitly defined.
     */
    public function applyDefaultHeaders(): void
    {
        if (!isset($this->headers['Cache-Control'])) {
            $this->headers['Cache-Control'] = DownloadConfig::DEFAULT_CACHE_CONTROL;
        }
    }

    /**
     * @throws DownloadException
     */
    private static function validateHeaderName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || !preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            throw DownloadException::invalidHeaderName($name);
        }

        $normalized = ucwords(strtolower($name), '-');

        if (!in_array($normalized, DownloadConfig::VALID_HEADERS, true)) {
            throw DownloadException::invalidHeaderName($name);
        }

        return $normalized;
    }

    /**
     * Validate an HTTP header value.
     *
     * CR and LF characters are forbidden to prevent HTTP header injection.
     *
     * @throws DownloadException
     */
    private static function validateHeaderValue(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw DownloadException::invalidHeaderValue($value);
        }

        return $value;
    }

    /**
     * Build a safe ASCII fallback for the Content-Disposition filename.
     *
     * Control characters are removed first. The /u modifier is deliberately
     * avoided so that invalid UTF-8 byte sequences do not cause preg_replace()
     * to fail.
     */
    private static function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?? '';
        $filename = trim($filename);

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        $basename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename);

        if ($basename === false) {
            $basename = '';
        }

        $basename = preg_replace('/[^\x20-\x7E]/', '', $basename) ?? '';
        $basename = trim($basename);

        if ($basename === '') {
            $basename = 'download';
        }

        $result = $basename;

        if ($extension !== '') {
            $extension = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $extension);

            if ($extension !== false) {
                $extension = preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '';

                if ($extension !== '') {
                    $result .= '.' . $extension;
                }
            }
        }

        return addcslashes($result, "\"\\");
    }
}
