<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Range\DownloadRangeResolver;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class DownloadRangeResolverTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $server;

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
        $resource = $this->resource();
        $etag = $this->etag($resource);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertFalse($range->isMultipart());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(99, $item->getEnd());
        self::assertSame(100, $item->getLength());
    }

    public function testItProcessesRangeWhenIfRangeIsAbsent(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(9, $item->getEnd());
    }

    public function testItProcessesRangeWhenIfRangeMatchesEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $_SERVER['HTTP_IF_RANGE'] = $etag->getValue();

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());

        self::assertSame(
            0,
            $range->getFirstItem()->getStart()
        );

        self::assertSame(
            9,
            $range->getFirstItem()->getEnd()
        );
    }

    public function testItReturnsFullRangeWhenIfRangeDoesNotMatchEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = '"etag-inexistant"';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(99, $range->getFirstItem()->getEnd());
    }

    public function testItReturnsFullRangeWhenIfRangeUsesWeakEtag(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $_SERVER['HTTP_IF_RANGE'] = 'W/' . $etag->getValue();

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(99, $range->getFirstItem()->getEnd());
    }

    public function testItProcessesRangeWhenIfRangeDateMatchesLastModified(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $lastModified = 1000;
        $resource = $this->resource($lastModified);
        $etag = $this->etag($resource);

        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate($lastModified);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(9, $range->getFirstItem()->getEnd());
    }

    public function testItReturnsFullRangeWhenIfRangeDateDiffersFromLastModified(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';

        $resource = $this->resource(1000);
        $etag = $this->etag($resource);

        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate(999);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(99, $range->getFirstItem()->getEnd());
    }

    public function testItReturnsFullRangeWhenIfRangeDateIsInvalid(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = 'invalid-date';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(99, $range->getFirstItem()->getEnd());
    }

    public function testItReturnsFullRangeWhenIfRangeDateIsPresentButLastModifiedIsUnavailable(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9';
        $_SERVER['HTTP_IF_RANGE'] = $this->httpDate(1000);

        $resource = $this->resource(null);
        $etag = $this->etag($resource);

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->getFirstItem()->getStart());
        self::assertSame(99, $range->getFirstItem()->getEnd());
    }

    public function testItSupportsMultipleRangesWhenIfRangeMatches(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-9,20-29';

        $resource = $this->resource();
        $etag = $this->etag($resource);

        $_SERVER['HTTP_IF_RANGE'] = $etag->getValue();

        $result = DownloadRangeResolver::create(
            $resource,
            DownloadRequest::create(),
            $etag,
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());
        self::assertTrue($range->isMultipart());
        self::assertNotNull($range->getBoundary());
    }

    /**
     * @param int|null $lastModified
     */
    private function resource(?int $lastModified = 1000): DownloadResource
    {
        return new readonly class($lastModified) implements DownloadResource {
            public function __construct(
                private ?int $lastModified,
            ) {
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
                return null;
            }

            public function getSupportedETagStrategies(): array
            {
                return [
                    ETagStrategy::MTIME,
                ];
            }
        };
    }

    private function etag(DownloadResource $resource): DownloadETag
    {
        return DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );
    }

    private function httpDate(int $timestamp): string
    {
        return gmdate(
                'D, d M Y H:i:s',
                $timestamp
            ) . ' GMT';
    }
}
