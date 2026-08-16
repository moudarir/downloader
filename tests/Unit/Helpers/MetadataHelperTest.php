<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Helpers;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Helpers\MetadataHelper;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class MetadataHelperTest extends TestCase
{

    public function testItReturnsResourceMetadata(): void
    {
        $resource = $this->resource(
            filepath: '/tmp/video.mov',
            filename: 'video.mov',
            filesize: 1000,
            mime: 'video/quicktime',
            lastModified: 1786274279,
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $this->etag($resource),
            200,
            1000,
            'video/quicktime',
        );

        self::assertSame(
            '/tmp/video.mov',
            $metadata->filepath()
        );

        self::assertSame(
            'video.mov',
            $metadata->filename()
        );

        self::assertSame(
            1000,
            $metadata->filesize()
        );

        self::assertSame(
            'video/quicktime',
            $metadata->mimeType()
        );

        self::assertSame(
            1786274279,
            $metadata->lastModified()
        );
    }

    public function testItReturnsResponseMetadata(): void
    {
        $resource = $this->resource();

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::PARTIAL,
            $this->etag($resource),
            206,
            25,
            'video/mp4',
        );

        self::assertSame(
            206,
            $metadata->statusCode()
        );

        self::assertSame(
            25,
            $metadata->contentLength()
        );

        self::assertSame(
            'video/mp4',
            $metadata->contentType()
        );

        self::assertSame(
            ResponseAction::PARTIAL,
            $metadata->responseAction()
        );
    }

    public function testItReturnsEtagMetadata(): void
    {
        $resource = $this->resource();

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $etag,
            200,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertSame(
            $etag->getOpaqueValue(),
            $metadata->etagValue()
        );

        self::assertFalse(
            $metadata->etagIsWeak()
        );
    }

    public function testItReturnsWeakEtagMetadata(): void
    {
        $resource = $this->resource();

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MTIME,
            true
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $etag,
            200,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertSame(
            $etag->getOpaqueValue(),
            $metadata->etagValue()
        );

        self::assertTrue(
            $metadata->etagIsWeak()
        );
    }

    public function testItReturnsNullFilepathForInMemoryResource(): void
    {
        $resource = $this->resource(
            filepath: null
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $this->etag($resource),
            200,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertNull(
            $metadata->filepath()
        );
    }

    public function testItReturnsNullLastModifiedWhenResourceDoesNotHaveOne(): void
    {
        $resource = $this->resource(
            lastModified: null
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $this->etag($resource),
            200,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertNull(
            $metadata->lastModified()
        );
    }

    public function testItReturnsNullContentTypeWhenNoneWasProvided(): void
    {
        $resource = $this->resource();

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $this->etag($resource),
            304,
            0,
        );

        self::assertNull(
            $metadata->contentType()
        );
    }

    public function testItReturnsNoRangeWhenRangeIsAbsent(): void
    {
        $resource = $this->resource();

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            $this->etag($resource),
            200,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertFalse(
            $metadata->hasRange()
        );

        self::assertFalse(
            $metadata->rangeIsPartial()
        );

        self::assertFalse(
            $metadata->rangeIsMultipart()
        );

        self::assertNull(
            $metadata->rangeItems()
        );
    }

    public function testItReturnsSingleRangeMetadata(): void
    {
        $resource = $this->resource();

        $item = new DownloadRangeItem(10, 19);

        $range = DownloadRange::partial(
            [$item],
            null
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::PARTIAL,
            $this->etag($resource),
            206,
            10,
            $resource->getMime(),
            $range,
        );

        self::assertTrue(
            $metadata->hasRange()
        );

        self::assertTrue(
            $metadata->rangeIsPartial()
        );

        self::assertFalse(
            $metadata->rangeIsMultipart()
        );

        $items = $metadata->rangeItems();

        self::assertNotNull($items);
        self::assertCount(1, $items);
        self::assertSame($item, $items[0]);
    }

    public function testItReturnsMultipartRangeMetadata(): void
    {
        $resource = $this->resource();

        $first = new DownloadRangeItem(0, 9);
        $second = new DownloadRangeItem(20, 29);

        $range = DownloadRange::partial(
            [$first, $second],
            'test-boundary'
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::PARTIAL,
            $this->etag($resource),
            206,
            123,
            'multipart/byteranges; boundary=test-boundary',
            $range,
        );

        self::assertTrue(
            $metadata->hasRange()
        );

        self::assertTrue(
            $metadata->rangeIsPartial()
        );

        self::assertTrue(
            $metadata->rangeIsMultipart()
        );

        $items = $metadata->rangeItems();

        self::assertNotNull($items);
        self::assertCount(2, $items);
        self::assertSame($first, $items[0]);
        self::assertSame($second, $items[1]);
    }

    public function testItPreservesConfiguredValuesWithoutRecomputingThem(): void
    {
        $resource = $this->resource(
            filesize: 1000,
            mime: 'video/quicktime'
        );

        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::PARTIAL,
            $this->etag($resource),
            206,
            37,
            'multipart/byteranges; boundary=test-boundary',
            DownloadRange::partial(
                [
                    new DownloadRangeItem(0, 9),
                    new DownloadRangeItem(20, 29),
                ],
                'test-boundary'
            ),
        );

        self::assertSame(206, $metadata->statusCode());
        self::assertSame(37, $metadata->contentLength());
        self::assertSame(
            'multipart/byteranges; boundary=test-boundary',
            $metadata->contentType()
        );
    }

    private function etag(DownloadResource $resource): DownloadETag
    {
        return DownloadETag::create(
            $resource,
            ETagStrategy::MTIME
        );
    }

    private function resource(
        ?string $filepath = '/tmp/test.bin',
        string $filename = 'test.bin',
        int $filesize = 100,
        string $mime = 'application/octet-stream',
        ?int $lastModified = 1786274279,
    ): DownloadResource {
        return new readonly class(
            $filepath,
            $filename,
            $filesize,
            $mime,
            $lastModified,
        ) implements DownloadResource {
            public function __construct(
                private ?string $filepath,
                private string  $filename,
                private int     $filesize,
                private string  $mime,
                private ?int    $lastModified,
            ) {
            }

            public function getFilename(): string
            {
                return $this->filename;
            }

            public function getFilesize(): int
            {
                return $this->filesize;
            }

            public function getMime(): string
            {
                return $this->mime;
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
                return $this->filepath;
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
}
