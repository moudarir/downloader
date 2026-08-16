<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class DownloadETagTest extends TestCase
{

    public function testCreateWithMtimeStrategy(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertSame('"7b-1c8"', $etag->getValue());
        self::assertSame('7b-1c8', $etag->getOpaqueValue());
        self::assertFalse($etag->isWeak());
    }

    public function testCreateWeakEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME,
            true
        );

        self::assertSame('W/"7b-1c8"', $etag->getValue());
        self::assertSame('7b-1c8', $etag->getOpaqueValue());
        self::assertTrue($etag->isWeak());
    }

    public function testCreateWithHashStrategy(): void
    {
        $hash = md5('test data');

        $resource = $this->resource(
            filesize: 9,
            lastModified: 123,
            hash: $hash,
            strategies: [
                ETagStrategy::MD5,
            ],
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );

        self::assertSame('"' . $hash . '"', $etag->getValue());
        self::assertSame($hash, $etag->getOpaqueValue());
    }

    public function testCreateThrowsWhenNoStrategyIsSupported(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
            strategies: [],
        );

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'does not declare any supported ETag strategy.'
        );

        DownloadETag::create($resource);
    }

    public function testCreateThrowsWhenRequestedStrategyIsUnsupported(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
            strategies: [
                ETagStrategy::MTIME,
            ],
        );

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'ETag strategy `md5` is not supported by resource'
        );

        DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );
    }

    public function testCreateThrowsWhenHashStrategyFails(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
            hash: null,
            strategies: [
                ETagStrategy::MD5,
            ],
        );

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Unable to generate an ETag using the `md5` strategy.'
        );

        DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );
    }

    public function testMatchesExactStrongEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertTrue(
            $etag->matches('"7b-1c8"')
        );
    }

    public function testMatchesWeakClientEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertTrue(
            $etag->matches('W/"7b-1c8"')
        );
    }

    public function testStrongComparisonRejectsWeakClientEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertFalse(
            $etag->matches('W/"7b-1c8"', false)
        );
    }

    public function testStrongComparisonRejectsWeakResourceEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME,
            true
        );

        self::assertFalse(
            $etag->matches('"7b-1c8"', false)
        );
    }

    public function testMatchesMultipleEtags(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertTrue(
            $etag->matches('"etag-1", "7b-1c8", "etag-2"')
        );
    }

    public function testMatchesWildcard(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertTrue(
            $etag->matches('*')
        );
    }

    public function testDoesNotMatchDifferentEtag(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertFalse(
            $etag->matches('"etag-inexistant"')
        );
    }

    public function testEqualsReturnsTrueForIdenticalEtags(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $first = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        $second = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        self::assertTrue(
            $first->equals($second)
        );
    }

    public function testEqualsReturnsFalseWhenWeaknessDiffers(): void
    {
        $resource = $this->resource(
            filesize: 456,
            lastModified: 123,
        );

        $strong = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME,
            false
        );

        $weak = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME,
            true
        );

        self::assertFalse(
            $strong->equals($weak)
        );
    }

    /**
     * @param list<ETagStrategy> $strategies
     */
    private function resource(
        int $filesize,
        ?int $lastModified,
        ?string $hash = null,
        array $strategies = [
            ETagStrategy::MTIME,
            ETagStrategy::MD5,
        ],
    ): DownloadResource {
        return new readonly class(
            $filesize,
            $lastModified,
            $hash,
            $strategies,
        ) implements DownloadResource {
            /**
             * @param list<ETagStrategy> $strategies
             */
            public function __construct(
                private int     $filesize,
                private ?int    $lastModified,
                private ?string $hash,
                private array   $strategies,
            ) {
            }

            public function getFilename(): string
            {
                return 'test.bin';
            }

            public function getFilesize(): int
            {
                return $this->filesize;
            }

            public function getMime(): string
            {
                return 'application/octet-stream';
            }

            public function getLastModified(): ?int
            {
                return $this->lastModified;
            }

            public function output(int $length, int $start = 0): void
            {
            }

            public function getFilepath(): ?string
            {
                return null;
            }

            public function getHash(string $algorithm): ?string
            {
                return $this->hash;
            }

            /**
             * @return list<ETagStrategy>
             */
            public function getSupportedETagStrategies(): array
            {
                return $this->strategies;
            }
        };
    }
}
