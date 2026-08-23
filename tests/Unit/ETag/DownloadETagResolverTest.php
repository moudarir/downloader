<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETagResolver;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Tests\Support\FixtureData;
use PHPUnit\Framework\TestCase;

final class DownloadETagResolverTest extends TestCase
{

    public function testItThrowsWhenResourceDoesNotSupportAnyStrategy(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("does not declare any supported ETag strategy.");

        DownloadETagResolver::resolve(FixtureData::create([]));
    }

    public function testItReturnsFirstSupportedStrategyWhenNoStrategyIsRequested(): void
    {
        $resource = FixtureData::create([ETagStrategy::SHA256, ETagStrategy::MD5]);

        self::assertSame(
            ETagStrategy::SHA256,
            DownloadETagResolver::resolve($resource)
        );
    }

    public function testItReturnsRequestedSupportedStrategy(): void
    {
        $resource = FixtureData::create([ETagStrategy::MTIME, ETagStrategy::SHA256, ETagStrategy::MD5]);

        self::assertSame(
            ETagStrategy::SHA256,
            DownloadETagResolver::resolve($resource, ETagStrategy::SHA256)
        );
    }

    public function testItThrowsWhenRequestedStrategyIsNotSupported(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("ETag strategy `inode` is not supported by resource");

        DownloadETagResolver::resolve(FixtureData::create(), ETagStrategy::INODE);
    }
}
