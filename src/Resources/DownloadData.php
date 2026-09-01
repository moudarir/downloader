<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use Moudarir\File\Enum\MimeType;
use ValueError;

final readonly class DownloadData implements DownloadResource
{

    use StreamOutputTrait;

    /**
     * @var list<ETagStrategy>
     */
    private const array SUPPORTED_ETAG_STRATEGIES = [
        ETagStrategy::MD5,
        ETagStrategy::SHA256,
        ETagStrategy::SHA512,
    ];

    private function __construct(private string $data, private string $filename, private ?string $mime = null)
    {
    }

    /**
     * @throws DownloadException
     */
    public static function create(string $data, string $filename, ?string $mime = null): self
    {
        if (CommonHelper::nullIfEmpty($data) === null) {
            throw DownloadException::emptyDataSource();
        }

        if (CommonHelper::nullIfEmpty($filename) === null) {
            throw DownloadException::filenameRequiredForData();
        }

        return new self($data, $filename, $mime);
    }

    public function getFilename(): string
    {
        return trim($this->filename);
    }

    public function getMime(): string
    {
        return $this->mime ?: MimeType::OCTET_STREAM->value;
    }

    public function getFilesize(): int
    {
        return strlen($this->data);
    }

    public function getLastModified(): ?int
    {
        return null;
    }

    public function getFilepath(): ?string
    {
        return null;
    }

    public function getInternalUri(): ?string
    {
        return null;
    }

    public function getHash(string $algorithm): ?string
    {
        try {
            return hash($algorithm, $this->data);
        } catch (ValueError) {
            return null;
        }
    }

    public function getSupportedETagStrategies(): array
    {
        return self::SUPPORTED_ETAG_STRATEGIES;
    }

    public function output(DownloadConfig $config, int $length, int $start = 0): void
    {
        if ($length <= 0 || empty($this->data)) {
            return;
        }

        // `php://temp`: keeps data in RAM up to 2MB, then switches to a temporary disk.
        if (($handle = fopen('php://temp', 'r+')) === false) {
            return;
        }

        try {
            fwrite($handle, $this->data);
            $this->outputStream(
                $handle,
                $length,
                $start,
                $config->getBytesPerSecond(),
                $config->getChunkSize(),
            );
        } finally {
            fclose($handle);
        }
    }
}
