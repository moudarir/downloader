<?php

declare(strict_types=1);

namespace Moudarir\Downloader\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadResource;

final readonly class DownloadETag
{

    private function __construct(private string $value, private bool $weak)
    {
    }

    /**
     * @throws DownloadException
     */
    public static function create(
        DownloadResource $resource,
        ETagStrategy $strategy = ETagStrategy::MTIME,
        bool $weak = false
    ): self
    {
        return new self(self::calculate($resource, $strategy), $weak);
    }

    public function getValue(): string
    {
        return $this->weak ? sprintf('W/"%s"', $this->value) : sprintf('"%s"', $this->value);
    }

    public function getOpaqueValue(): string
    {
        return $this->value;
    }

    public function isWeak(): bool
    {
        return $this->weak;
    }

    public function matches(string $clientETagHeader, bool $weakComparison = true): bool
    {
        if (($clientETagHeader = trim($clientETagHeader)) === '*') {
            return true;
        }

        if (!$weakComparison && $this->weak) {
            return false;
        }

        $tags = array_map('trim', explode(',', $clientETagHeader));

        foreach ($tags as $tag) {
            $isClientWeak = str_starts_with($tag, 'W/');

            if (!$weakComparison && $isClientWeak) {
                continue;
            }

            $opaqueTag = trim($tag, 'W/"');

            if (hash_equals($this->value, $opaqueTag)) {
                return true;
            }
        }

        return false;
    }

    public function equals(self $etag): bool
    {
        return $this->weak === $etag->weak && $this->value === $etag->value;
    }

    /**
     * @throws DownloadException
     */
    private static function calculate(DownloadResource $resource, ETagStrategy $strategy): string
    {
        $result = match ($strategy) {
            ETagStrategy::INODE => self::inodeStrategy($resource),
            ETagStrategy::MD5,
            ETagStrategy::SHA256,
            ETagStrategy::SHA512 => $resource->getHash($strategy->value),
            default => self::mtimeStrategy($resource),
        };

        if ($result === null) {
            throw DownloadException::eTagStrategyFailed($strategy->value);
        }

        return $result;
    }

    private static function mtimeStrategy(DownloadResource $resource): string
    {
        return sprintf('%x-%x', $resource->getLastModified() ?? 0, $resource->getFilesize());
    }

    private static function inodeStrategy(DownloadResource $resource): ?string
    {
        $filepath = $resource->getFilepath();

        if ($filepath === null || ($inode = fileinode($filepath)) === false) {
            return null;
        }

        return sprintf('%x-%x-%x', $inode, $resource->getLastModified() ?? 0, $resource->getFilesize());
    }
}
