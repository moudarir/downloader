<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\MimeDetector\Exceptions\MimeTypeException;
use Moudarir\MimeDetector\MimeType;

final readonly class DownloadFile implements DownloadResource
{

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
    public static function create(string $filepath, ?string $filename = null, bool $detectMime = false): self
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
            self::detectMimeType($filepath, $detectMime),
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
        if (($hash = hash_file($algorithm, $this->filepath)) === false) {
            return null;
        }

        return $hash;
    }

    public function output(int $length, int $start = 0): void
    {
        if (($handle = fopen($this->filepath, 'rb')) === false) {
            return;
        }

        try {
            if ($start > 0 && fseek($handle, $start) !== 0) {
                return;
            }

            $bytesRemaining = $length;

            while ($bytesRemaining > 0 && !feof($handle)) {
                $chunkSize = min(DownloadConfig::CHUNK_SIZE, $bytesRemaining);
                $buffer = fread($handle, $chunkSize);

                if ($buffer === false || $buffer === '') {
                    break;
                }

                echo $buffer;

                $bytesRemaining -= strlen($buffer);

                if (connection_aborted() !== 0) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function getSupportedETagStrategies(): array
    {
        return self::SUPPORTED_ETAG_STRATEGIES;
    }

    /**
     * @throws DownloadException
     */
    private static function detectMimeType(string $filepath, bool $detectMime): string
    {
        if ($detectMime === false) {
            return DownloadConfig::DEFAULT_MIME;
        }

        try {
            return MimeType::detect($filepath)->value();
        } catch (MimeTypeException $exception) {
            throw DownloadException::generic($exception->getMessage());
        }
    }
}
