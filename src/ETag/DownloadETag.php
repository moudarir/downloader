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
        ?ETagStrategy $strategy = null,
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

    /**
     * RFC 9110 - Entity Tag comparison.
     *
     * Performs either a weak or strong comparison depending on the
     * $weakComparison argument.
     */
    public function matches(string $clientETagHeader, bool $weakComparison = true): bool
    {
        $clientETagHeader = trim($clientETagHeader);

        if ($clientETagHeader === '*') {
            return true;
        }

        if (!$weakComparison && $this->weak) {
            return false;
        }

        foreach (explode(',', $clientETagHeader) as $tag) {
            $tag = trim($tag);
            $isClientWeak = strncmp($tag, 'W/', 2) === 0;

            if (!$weakComparison && $isClientWeak) {
                continue;
            }

            if ($isClientWeak) {
                $tag = substr($tag, 2);
            }

            if (str_starts_with($tag, '"') && str_ends_with($tag, '"')) {
                $tag = substr($tag, 1, -1);
            }

            if (hash_equals($this->value, $tag)) {
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
    private static function calculate(DownloadResource $resource, ?ETagStrategy $strategy = null): string
    {
        $resolve = DownloadETagResolver::resolve($resource, $strategy);
        $result = match ($resolve) {
            ETagStrategy::INODE => self::inodeStrategy($resource),
            ETagStrategy::MD5,
            ETagStrategy::SHA256,
            ETagStrategy::SHA512 => $resource->getHash($resolve->value),
            ETagStrategy::MTIME => self::mtimeStrategy($resource),
        };

        if ($result === null) {
            throw DownloadException::eTagStrategyFailed($resolve->value);
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
