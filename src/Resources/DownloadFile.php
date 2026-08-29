<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use Moudarir\MimeDetector\Detector;
use Moudarir\MimeDetector\Exceptions\MimeDetectorException;
use Moudarir\MimeDetector\FileMetadata;
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
        private string $mime,
        private FileMetadata $metadata,
        private ?string $internalUri = null,
    ) {
    }

    /**
     * @throws DownloadException
     */
    public static function create(
        string $filepath,
        ?string $filename = null,
        true|string $mime = '',
        ?string $internalUri = null,
    ): self
    {
        $filepath = CommonHelper::nullIfEmpty($filepath);

        if ($filepath === null) {
            throw DownloadException::filepathNotFound();
        }

        try {
            $result = Detector::detect($filepath);
            $metadata = $result->metadata();

            $filename = CommonHelper::nullIfEmpty($filename);
            $filename = $filename === null ? $metadata->basename() : $filename;

            if (is_string($mime)) {
                $mime = trim($mime);
                $mimeType = $mime !== '' ? $mime : $result->value();
            } else {
                $mimeType = $result->value();
            }

            return new self(
                $filepath,
                $filename,
                $mimeType,
                $metadata,
                $internalUri
            );
        } catch (MimeDetectorException $exception) {
            throw DownloadException::generic("Error encountered", $exception);
        }
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
        return $this->metadata->filesize();
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getLastModified(): int
    {
        return $this->metadata->lastModified();
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
        $this->outputStream(
            $this->metadata->stream(),
            $length,
            $start,
            $config->getBytesPerSecond(),
            $config->getChunkSize(),
        );
    }

    public function getSupportedETagStrategies(): array
    {
        return self::SUPPORTED_ETAG_STRATEGIES;
    }
}
