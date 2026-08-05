<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;

final readonly class DownloadData implements DownloadResource
{

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
        return $this->mime ?: DownloadConfig::DEFAULT_MIME;
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

    public function getHash(string $algorithm): ?string
    {
        try {
            return hash($algorithm, $this->data);
        } catch (\ValueError $exception) {
            return null;
        }
    }

    public function getSupportedETagStrategies(): array
    {
        return self::SUPPORTED_ETAG_STRATEGIES;
    }

    public function output(int $length, int $start = 0): void
    {
        if ($length <= 0) {
            return;
        }

        $bytesSent = 0;

        while ($bytesSent < $length) {
            if (connection_aborted() !== 0) {
                break;
            }

            $readLength = min(DownloadConfig::CHUNK_SIZE, $length - $bytesSent);

            echo substr($this->data, $start + $bytesSent, $readLength);

            $bytesSent += $readLength;
        }
    }
}
