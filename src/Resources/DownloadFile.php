<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use Moudarir\Downloader\Traits\StreamOutputTrait;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\Exceptions\FileResourceException;
use Moudarir\File\Exceptions\MimeDetectionException;
use Moudarir\File\File;
use Moudarir\File\FileResource;
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
        private string       $filepath,
        private string       $filename,
        private string       $mime,
        private FileResource $resource,
        private ?string      $internalUri = null,
    ) {
    }

    /**
     * @throws DownloadException
     */
    public static function create(
        string $filepath,
        ?string $filename = null,
        ?MimeType $mime = null,
        ?string $internalUri = null,
    ): self
    {
        $filepath = CommonHelper::nullIfEmpty($filepath);

        if ($filepath === null) {
            throw DownloadException::filepathNotFound();
        }

        try {
            $file = File::create($filepath, $mime);
            $resource = $file->resource();

            $filename = CommonHelper::nullIfEmpty($filename);
            $filename = $filename === null ? $resource->basename() : $filename;

            return new self(
                $filepath,
                $filename,
                $file->detection()->mimeTypeValue(),
                $resource,
                $internalUri
            );
        } catch (FileResourceException|MimeDetectionException $exception) {
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
        return $this->resource->filesize();
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getLastModified(): int
    {
        return $this->resource->lastModified();
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
            $this->resource->stream(),
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
