<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETagResolver;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class DownloadETagResolverTest extends TestCase
{

    public function testItThrowsWhenResourceDoesNotSupportAnyStrategy(): void
    {
        $resource = $this->resource([]);

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("does not declare any supported ETag strategy.");

        DownloadETagResolver::resolve($resource);
    }

    public function testItReturnsFirstSupportedStrategyWhenNoStrategyIsRequested(): void
    {
        $resource = $this->resource([ETagStrategy::SHA256, ETagStrategy::MD5,]);

        self::assertSame(
            ETagStrategy::SHA256,
            DownloadETagResolver::resolve($resource)
        );
    }

    public function testItReturnsRequestedSupportedStrategy(): void
    {
        $resource = $this->resource([
            ETagStrategy::MTIME,
            ETagStrategy::SHA256,
            ETagStrategy::MD5,
        ]);

        self::assertSame(
            ETagStrategy::SHA256,
            DownloadETagResolver::resolve($resource, ETagStrategy::SHA256)
        );
    }

    public function testItAcceptsTheFirstStrategyWhenExplicitlyRequested(): void
    {
        $resource = $this->resource([ETagStrategy::INODE, ETagStrategy::MD5,]);

        self::assertSame(
            ETagStrategy::INODE,
            DownloadETagResolver::resolve($resource, ETagStrategy::INODE)
        );
    }

    public function testItAcceptsTheLastSupportedStrategyWhenExplicitlyRequested(): void
    {
        $resource = $this->resource([
            ETagStrategy::MTIME,
            ETagStrategy::MD5,
            ETagStrategy::SHA512,
        ]);

        self::assertSame(
            ETagStrategy::SHA512,
            DownloadETagResolver::resolve($resource, ETagStrategy::SHA512)
        );
    }

    public function testItThrowsWhenRequestedStrategyIsNotSupported(): void
    {
        $resource = $this->resource([ETagStrategy::MTIME, ETagStrategy::SHA256,]);

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("ETag strategy `md5` is not supported by resource");

        DownloadETagResolver::resolve($resource, ETagStrategy::MD5);
    }

    /**
     * @param list<ETagStrategy> $strategies
     */
    private function resource(array $strategies): DownloadResource
    {
        return new readonly class($strategies) implements DownloadResource
        {

            /**
             * @param list<ETagStrategy> $strategies
             */
            public function __construct(private array $strategies)
            {
            }

            public function getFilename(): string
            {
                return 'test.bin';
            }

            public function getFilesize(): int
            {
                return 100;
            }

            public function getMime(): string
            {
                return 'application/octet-stream';
            }

            public function getLastModified(): ?int
            {
                return 123;
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
                return null;
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
