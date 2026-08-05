<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use function get_mimes;

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

    private static function detectMimeType(string $filepath, bool $detectMime): string
    {
        if ($detectMime === false) {
            return DownloadConfig::DEFAULT_MIME;
        }

        if (($finfoOpen = finfo_open(FILEINFO_MIME_TYPE)) !== false) {
            $finfoFile = finfo_file($finfoOpen, $filepath);
            finfo_close($finfoOpen);

            if ($finfoFile !== false && $finfoFile !== '') {
                return strtolower(trim($finfoFile));
            }
        }

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        if ($extension === '') {
            return DownloadConfig::DEFAULT_MIME;
        }

        $mimes =& get_mimes();

        if (array_key_exists($extension, $mimes)) {
            return is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
        }

        return DownloadConfig::DEFAULT_MIME;
    }
}
