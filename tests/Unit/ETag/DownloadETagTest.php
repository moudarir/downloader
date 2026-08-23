<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Tests\Support\FixtureData;
use Moudarir\Downloader\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class DownloadETagTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private static array $fixture;

    private static FixtureData $resource;

    public static function setUpBeforeClass(): void
    {
        self::$fixture = TestConfig::resourceData();
        self::$resource = FixtureData::create();
    }

    public function testCreateWithMtimeStrategy(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertSame(
            ['"'.self::$fixture['etag'].'"', self::$fixture['etag']],
            [$etag->getValue(), $etag->getOpaqueValue()]
        );
        self::assertFalse($etag->isWeak());
    }

    public function testCreateWeakEtag(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5, true);

        self::assertSame(
            ['W/"'.self::$fixture['etag'].'"', self::$fixture['etag']],
            [$etag->getValue(), $etag->getOpaqueValue()]
        );
        self::assertTrue($etag->isWeak());
    }

    public function testCreateWithHashStrategy(): void
    {
        $hash = self::$resource->getHash('md5');
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertSame(
            ['"' . $hash . '"', $hash],
            [$etag->getValue(), $etag->getOpaqueValue()]
        );
    }

    public function testCreateThrowsWhenNoStrategyIsSupported(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("does not declare any supported ETag strategy.");

        DownloadETag::create(FixtureData::create([]));
    }

    public function testCreateThrowsWhenRequestedStrategyIsUnsupported(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("ETag strategy `mtime` is not supported by resource");

        DownloadETag::create(self::$resource, ETagStrategy::MTIME);
    }

    public function testMatchesEtagComparaisons(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertTrue($etag->matches('"'.self::$fixture['etag'].'"'));
        self::assertTrue($etag->matches('W/"'.self::$fixture['etag'].'"'));
        self::assertFalse($etag->matches('W/"'.self::$fixture['etag'].'"', false));

        $weak = DownloadETag::create(self::$resource, ETagStrategy::MD5, true);

        self::assertFalse($weak->matches('"'.self::$fixture['etag'].'"', false));
    }

    public function testMatchesMultipleEtags(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertTrue($etag->matches('"etag-1", "'.self::$fixture['etag'].'", "etag-2"'));
    }

    public function testMatchesWildcard(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertTrue($etag->matches('*'));
    }

    public function testDoesNotMatchDifferentEtag(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertFalse($etag->matches('"etag-inexistant"'));
    }

    public function testEqualsReturnsTrueForIdenticalEtags(): void
    {
        $first = DownloadETag::create(self::$resource, ETagStrategy::MD5);
        $second = DownloadETag::create(self::$resource, ETagStrategy::MD5);

        self::assertTrue($first->equals($second));
    }

    public function testEqualsReturnsFalseWhenWeaknessDiffers(): void
    {
        $strong = DownloadETag::create(self::$resource, ETagStrategy::MD5);
        $weak = DownloadETag::create(self::$resource, ETagStrategy::MD5, true);

        self::assertFalse($strong->equals($weak));
    }
}
