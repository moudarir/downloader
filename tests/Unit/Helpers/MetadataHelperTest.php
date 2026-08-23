<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Helpers;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Helpers\MetadataHelper;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Resources\DownloadResource;
use Moudarir\Downloader\Tests\Support\FixtureData;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use PHPUnit\Framework\TestCase;

final class MetadataHelperTest extends TestCase
{

    private static DownloadResource $resource;

    private static DownloadETag $etag;

    public static function setUpBeforeClass(): void
    {
        self::$resource = FixtureFile::create('bin');
        self::$etag = DownloadETag::create(self::$resource, ETagStrategy::MTIME);
    }

    public function testItReturnsResourceMetadata(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::DEFAULT,
            self::$etag,
            StatusCode::OK,
            self::$resource->getFilesize(),
            self::$resource->getMime(),
        );

        self::assertSame(
            [
                self::$resource->getFilepath(),
                self::$resource->getFilename(),
                self::$resource->getFilesize(),
                self::$resource->getMime(),
                self::$resource->getLastModified()
            ],
            [
                $metadata->filepath(),
                $metadata->filename(),
                $metadata->filesize(),
                $metadata->mimeType(),
                $metadata->lastModified()
            ]
        );
    }

    public function testItReturnsResponseMetadata(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::PARTIAL,
            self::$etag,
            StatusCode::PARTIAL_CONTENT,
            self::$resource->getFilesize(),
            self::$resource->getMime(),
        );

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT,
                self::$resource->getFilesize(),
                self::$resource->getMime(),
                ResponseAction::PARTIAL,
            ],
            [
                $metadata->statusCode(),
                $metadata->contentLength(),
                $metadata->contentType(),
                $metadata->responseAction(),
            ]
        );
    }

    public function testItReturnsEtagMetadata(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::DEFAULT,
            self::$etag,
            StatusCode::OK,
            self::$resource->getFilesize(),
            self::$resource->getMime(),
        );

        self::assertSame(
            [self::$etag->getValue(), self::$etag->getOpaqueValue()],
            [$metadata->etagValue(), $metadata->etagOpaqueValue()]
        );
        self::assertFalse($metadata->etagIsWeak());
    }

    public function testItReturnsWeakEtagMetadata(): void
    {
        $etag = DownloadETag::create(self::$resource, ETagStrategy::MTIME, true);
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::DEFAULT,
            $etag,
            StatusCode::OK,
            self::$resource->getFilesize(),
            self::$resource->getMime(),
        );

        self::assertSame(
            [$etag->getValue(), $etag->getOpaqueValue()],
            [$metadata->etagValue(), $metadata->etagOpaqueValue()]
        );
        self::assertTrue($metadata->etagIsWeak());
    }

    public function testItReturnsNullFilepathAndNullLastModifiedForInMemoryResource(): void
    {
        $resource = FixtureData::create();
        $metadata = MetadataHelper::create(
            $resource,
            ResponseAction::DEFAULT,
            DownloadETag::create($resource, ETagStrategy::MD5),
            StatusCode::OK,
            $resource->getFilesize(),
            $resource->getMime(),
        );

        self::assertNull($metadata->filepath());
        self::assertNull($metadata->lastModified());
    }

    public function testItReturnsNullContentTypeWhenNoneWasProvided(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::DEFAULT,
            self::$etag,
            StatusCode::NOT_MODIFIED,
            0,
        );

        self::assertNull($metadata->contentType());
    }

    public function testItReturnsNoRangeWhenRangeIsAbsent(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::DEFAULT,
            self::$etag,
            StatusCode::OK,
            self::$resource->getFilesize(),
            self::$resource->getMime(),
        );

        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
        self::assertNull($metadata->rangeItems());
    }

    public function testItReturnsSingleRangeMetadata(): void
    {
        $item = new DownloadRangeItem(10, 19);
        $range = DownloadRange::partial([$item], null);
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::PARTIAL,
            self::$etag,
            StatusCode::PARTIAL_CONTENT,
            10,
            self::$resource->getMime(),
            $range,
        );

        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());

        $rangeItems = $metadata->rangeItems();

        self::assertNotNull($rangeItems);
        self::assertCount(1, $rangeItems);
        self::assertSame($item, $rangeItems[0]);
    }

    public function testItReturnsMultipartRangeMetadata(): void
    {
        $first = new DownloadRangeItem(0, 9);
        $second = new DownloadRangeItem(20, 29);

        $range = DownloadRange::partial([$first, $second], 'test-boundary');
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::PARTIAL,
            self::$etag,
            StatusCode::PARTIAL_CONTENT,
            123,
            'multipart/byteranges; boundary=test-boundary',
            $range,
        );

        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertTrue($metadata->rangeIsMultipart());

        $rangeItems = $metadata->rangeItems();

        self::assertNotNull($rangeItems);
        self::assertCount(2, $rangeItems);
        self::assertSame([$first, $second], [$rangeItems[0], $rangeItems[1]]);
    }

    public function testItPreservesConfiguredValuesWithoutRecomputingThem(): void
    {
        $metadata = MetadataHelper::create(
            self::$resource,
            ResponseAction::PARTIAL,
            self::$etag,
            StatusCode::PARTIAL_CONTENT,
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

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT,
                'multipart/byteranges; boundary=test-boundary',
                37,
            ],
            [
                $metadata->statusCode(),
                $metadata->contentType(),
                $metadata->contentLength(),
            ]
        );
    }
}
