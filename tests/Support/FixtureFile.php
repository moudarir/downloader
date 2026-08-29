<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Support;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\FileHelper;
use Moudarir\Downloader\Resources\DownloadResource;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use ValueError;

final readonly class FixtureFile implements DownloadResource
{

    use StreamOutputTrait;

    /**
     * @var list<ETagStrategy>
     */
    private const array SUPPORTED_ETAG_STRATEGIES = [
        ETagStrategy::MTIME,
        ETagStrategy::INODE,
        ETagStrategy::MD5,
        ETagStrategy::SHA256,
        ETagStrategy::SHA512,
    ];

    private function __construct(
        private string $filepath,
        private string $filename,
        private int $filesize,
        private string $mime,
        private ?int $lastModified  = null,
        private ?int $internalUri  = null,
        private ?array $strategies = null,
    )
    {
    }

    /**
     * @throws DownloadException
     */
    public static function create(string $source, ?array $strategies = null): self
    {
        $fixture = TestConfig::resourceFile($source);
        $filepath = TestConfig::resourcePath() . $fixture['basename'];

        if (!is_file($filepath) || !is_readable($filepath)) {
            throw DownloadException::filepathNotFound();
        }

        if (($filesize = filesize($filepath)) === false) {
            throw DownloadException::filesizeIssue();
        }

        if (($lastModified = filemtime($filepath)) === false) {
            throw DownloadException::lastModifiedIssue();
        }

        return new self(
            $filepath,
            $fixture['filename'],
            $filesize,
            $fixture['mime'],
            $lastModified,
            null,
            $strategies
        );
    }

    public function getFilepath(): ?string
    {
        return $this->filepath;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFilesize(): int
    {
        return $this->filesize;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function getInternalUri(): ?string
    {
        return $this->internalUri;
    }

    public function getHash(string $algorithm): ?string
    {
        try {
            return hash_file($algorithm, $this->filepath);
        } catch (ValueError) {
            return null;
        }
    }

    public function output(DownloadConfig $config, int $length, int $start = 0): void
    {
        if (($handle = fopen($this->filepath, 'rb')) === false) {
            return;
        }

        try {
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
