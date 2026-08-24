<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Range\DownloadRangeResolver;
use Moudarir\Downloader\Range\DownloadRangeResult;
use Moudarir\Downloader\Resources\DownloadResource;
use Moudarir\Downloader\Tests\Support\FixtureData;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use PHPUnit\Framework\TestCase;

final class DownloadRangeResolverTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private array $server;

    private static FixtureFile $resource;

    private static DownloadETag $etag;

    private static int $filesize;

    private static int $lastModified;

    public static function setUpBeforeClass(): void
    {
        self::$resource = FixtureFile::create('txt');
        self::$etag = DownloadETag::create(self::$resource, ETagStrategy::MTIME);
        self::$filesize = self::$resource->getFilesize();
        self::$lastModified = self::$resource->getLastModified();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    public function testItReturnsFullRangeWhenRangeHeaderIsAbsent(): void
    {
        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertFalse($range->isMultipart());

        $firstItem = $range->getFirstItem();

        self::assertSame(
            [0, self::$filesize -1, self::$filesize],
            [$firstItem->getStart(), $firstItem->getEnd(), $firstItem->getLength()]
        );
    }

    public function testItProcessesRangeWhenIfRangeIsAbsent(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());

        $firstItem = $range->getFirstItem();

        self::assertSame([0, 9], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItProcessesRangeWhenIfRangeMatchesEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = self::$etag->getValue();

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertTrue($range->isPartial());
        self::assertSame([0, 9], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItReturnsFullRangeWhenIfRangeDoesNotMatchEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = '"etag-inexistant"';

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertFalse($range->isPartial());
        self::assertSame([0, self::$filesize -1], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItReturnsFullRangeWhenIfRangeUsesWeakEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = 'W/' . self::$etag->getValue();

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertFalse($range->isPartial());
        self::assertSame([0, self::$filesize -1], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItProcessesRangeWhenIfRangeDateMatchesLastModified(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate(self::$lastModified);

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertTrue($range->isPartial());
        self::assertSame([0, 9], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItReturnsFullRangeWhenIfRangeDateDiffersFromLastModified(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate(self::$lastModified - 1);

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertFalse($range->isPartial());
        self::assertSame([0, self::$filesize -1], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItReturnsFullRangeWhenIfRangeDateIsInvalid(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = 'invalid-date';

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertFalse($range->isPartial());
        self::assertSame([0, self::$filesize -1], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItReturnsFullRangeWhenIfRangeDateIsPresentButLastModifiedIsUnavailable(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate(1000);

        $resource = FixtureData::create();
        $result = $this->rangeResult($resource, DownloadETag::create($resource, ETagStrategy::MD5));

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();
        self::assertNotNull($range);

        $firstItem = $range->getFirstItem();
        self::assertFalse($range->isPartial());
        self::assertSame([0, $resource->getFilesize() - 1], [$firstItem->getStart(), $firstItem->getEnd()]);
    }

    public function testItSupportsMultipleRangesWhenIfRangeMatches(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9,20-29';
        $_SERVER['HTTP_IF_RANGE'] = self::$etag->getValue();

        $result = $this->rangeResult();

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());
        self::assertTrue($range->isMultipart());
        self::assertNotNull($range->getBoundary());
    }

    private function rangeResult(?DownloadResource $resource = null, ?DownloadETag $etag = null): DownloadRangeResult
    {
        return DownloadRangeResolver::create(
            $resource ?? self::$resource,
            DownloadRequest::create(),
                $etag ?? self::$etag,
            new DownloadConfig()
        );
    }

    private function httpDate(int $timestamp): string
    {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
