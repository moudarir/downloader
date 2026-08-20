<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Helpers\FileHelper;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use ValueError;

final readonly class DownloadFile implements DownloadResource
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
        private ?int $lastModified,
    ) {
    }

    /**
     * @throws DownloadException
     */
    public static function create(string $filepath, ?string $filename = null, true|string $mime = ''): self
    {
        $filepath = CommonHelper::nullIfEmpty($filepath);

        if ($filepath === null || !is_file($filepath) || !is_readable($filepath)) {
            throw DownloadException::filepathNotFound();
        }

        if (($filesize = filesize($filepath)) === false) {
            throw DownloadException::filesizeIssue();
        }

        $filename = CommonHelper::nullIfEmpty($filename);
        $filename = $filename === null
            ? pathinfo($filepath, PATHINFO_BASENAME)
            : $filename;

        return new self(
            $filepath,
            $filename,
            $filesize,
            FileHelper::detectMimeType($filepath, $mime),
            ($lastModified = filemtime($filepath)) === false ? null : $lastModified
        );
    }

    public function getFilepath(): string
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

    public function getHash(string $algorithm): ?string
    {
        try {
            return hash_file($algorithm, $this->filepath);
        } catch (ValueError) {
            return null;
        }
    }

    public function output(int $length, int $start = 0): void
    {
        if (($handle = fopen($this->filepath, 'rb')) === false) {
            return;
        }

        try {
            $this->outputStream($handle, $length, $start);
        } finally {
            fclose($handle);
        }
    }

    public function getSupportedETagStrategies(): array
    {
        return self::SUPPORTED_ETAG_STRATEGIES;
    }
}
