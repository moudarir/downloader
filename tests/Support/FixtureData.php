<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Support;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Helpers\FileHelper;
use Moudarir\Downloader\Resources\DownloadResource;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use ValueError;

final readonly class FixtureData implements DownloadResource
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

    private function __construct(
        private string $data,
        private string $filename,
        private ?string $mime = null,
        private ?array $strategies = null,
    )
    {
    }

    public static function create(?array $strategies = null): self
    {
        $fixture = TestConfig::resourceData();

        return new self(
            $fixture['content'],
            $fixture['filename'],
            $fixture['mime'],
            $strategies
        );
    }

    public function getFilepath(): ?string
    {
        return null;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFilesize(): int
    {
        return strlen($this->data);
    }

    public function getMime(): string
    {
        return $this->mime ?: DownloadConfig::DEFAULT_MIME;
    }

    public function getLastModified(): ?int
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

    public function output(DownloadConfig $config, int $length, int $start = 0): void
    {
        if ($length <= 0 || empty($this->data)) {
            return;
        }

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

    public function getSupportedETagStrategies(): array
    {
        return $this->strategies ?? self::SUPPORTED_ETAG_STRATEGIES;
    }

    public function contentDisposition(ContentDisposition $disposition = ContentDisposition::ATTACHMENT): string
    {
        return FileHelper::formatContentDisposition($this->filename, $disposition);
    }
}
