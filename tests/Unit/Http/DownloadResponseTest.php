<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Http\DownloadHeaders;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Http\DownloadResponse;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use PHPUnit\Framework\TestCase;

final class DownloadResponseTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private array $server;

    private static FixtureFile $resource;

    private static DownloadETag $etag;

    public static function setUpBeforeClass(): void
    {
        self::$resource = FixtureFile::create('txt');
        self::$etag = DownloadETag::create(self::$resource, ETagStrategy::MD5);
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

    public function testItCreatesDefaultResponseMetadata(): void
    {
        $response = $this->createResponse(ResponseAction::DEFAULT);
        $metadata = $response->metadata();

        self::assertNotNull($metadata->filepath());
        self::assertNotNull($metadata->lastModified());

        self::assertNotSame('', $metadata->etagValue());
        self::assertFalse($metadata->etagIsWeak());
        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
        self::assertSame(
            [
                StatusCode::OK,
                ResponseAction::DEFAULT,
                self::$resource->getFilename(),
                self::$resource->getFilesize(),
                self::$resource->getMime(),
            ],
            [
                $metadata->statusCode(),
                $metadata->responseAction(),
                $metadata->filename(),
                $metadata->contentLength(),
                $metadata->contentType(),
            ]
        );
    }

    public function testItCreatesPartialResponseForSingleRange(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4';

        $response = $this->createResponse();
        $metadata = $response->metadata();
        $rangeItem = $metadata->rangeItems()[0];

        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT,
                ResponseAction::PARTIAL,
                5,
                self::$resource->getMime(),
                0,
                4,
                5
            ],
            [
                $metadata->statusCode(),
                $metadata->responseAction(),
                $metadata->contentLength(),
                $metadata->contentType(),
                $rangeItem->getStart(),
                $rangeItem->getEnd(),
                $rangeItem->getLength()
            ]
        );
    }

    public function testItCreatesMultipartResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4,8-12';

        $response = $this->createResponse();
        $metadata = $response->metadata();
        $rangeItems = $metadata->rangeItems();

        self::assertNotNull($rangeItems);

        $firstRangeItem = $rangeItems[0];
        $secondRangeItem = $rangeItems[1];

        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertTrue($metadata->rangeIsMultipart());
        self::assertCount(2, $rangeItems);
        self::assertSame([0, 4], [$firstRangeItem->getStart(), $firstRangeItem->getEnd()]);
        self::assertSame([8, 12], [$secondRangeItem->getStart(), $secondRangeItem->getEnd()]);
        self::assertStringStartsWith('multipart/byteranges; boundary=', $metadata->contentType());
        self::assertGreaterThan(0, $metadata->contentLength());
    }

    public function testContentLengthForMultipartResponseMatchesMultipartStructure(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4,8-12';

        $response = $this->createResponse();
        $metadata = $response->metadata();

        $filepath = $metadata->filepath();
        $firstPart = file_get_contents($filepath, false, null, 0, 5);
        $secondPart = file_get_contents($filepath, false, null, 8, 5);

        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertSame(
            strlen(
                $this->buildExpectedMultipartBody(
                    boundary: $this->extractBoundary($metadata->contentType()),
                    mime: $metadata->mimeType(),
                    filesize: $metadata->filesize(),
                    parts: [[0, 4, $firstPart], [8, 12, $secondPart]],
                )
            ),
            $metadata->contentLength()
        );
    }

    public function testInvalidRangeFallsBackToFullResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=abc-def';

        $response = $this->createResponse();
        $metadata = $response->metadata();

        self::assertSame(StatusCode::OK, $metadata->statusCode());
        self::assertSame(self::$resource->getFilesize(), $metadata->contentLength());
        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
    }

    public function testUnsatisfiableRangeCreates416Response(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=1000-';
        $response = $this->createResponse();
        $metadata = $response->metadata();

        self::assertSame(StatusCode::RANGE_NOT_SATISFIABLE, $metadata->statusCode());
        self::assertSame(0, $metadata->contentLength());
        self::assertNull($metadata->contentType());
        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
    }

    public function testPreconditionResponseHasNoContent(): void
    {
        $response = DownloadResponse::precondition(
            StatusCode::NOT_MODIFIED,
            new DownloadHeaders(),
            self::$resource,
            DownloadRequest::create(),
            ResponseAction::DEFAULT,
            self::$etag,
        );

        $metadata = $response->metadata();

        self::assertNull($metadata->contentType());
        self::assertSame(
            [
                StatusCode::NOT_MODIFIED,
                ResponseAction::DEFAULT,
                0,
            ],
            [
                $metadata->statusCode(),
                $metadata->responseAction(),
                $metadata->contentLength(),
            ]
        );
    }

    public function testPreconditionFailedResponseHasNoContent(): void
    {
        $response = DownloadResponse::precondition(
            StatusCode::PRECONDITION_FAILED,
            new DownloadHeaders(),
            self::$resource,
            DownloadRequest::create(),
            ResponseAction::DEFAULT,
            self::$etag
        );

        $metadata = $response->metadata();

        self::assertNull($metadata->contentType());
        self::assertSame(
            [StatusCode::PRECONDITION_FAILED, 0],
            [$metadata->statusCode(), $metadata->contentLength()]
        );
    }

    public function testMetadataContentLengthIsAlreadyKnownBeforeSend(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=2-6';
        $response = $this->createResponse();

        self::assertSame(5, $response->metadata()->contentLength());
    }

    public function testMetadataReturnsRangeItemsFromResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-2,8-10';

        $response = $this->createResponse();
        $rangeItems = $response->metadata()->rangeItems();

        self::assertNotNull($rangeItems);
        self::assertCount(2, $rangeItems);
        self::assertInstanceOf(DownloadRangeItem::class, $rangeItems[0]);
        self::assertInstanceOf(DownloadRangeItem::class, $rangeItems[1]);
    }

    private function createResponse(ResponseAction $responseAction = ResponseAction::PARTIAL): DownloadResponse
    {
        return DownloadResponse::create(
            new DownloadHeaders(),
            self::$resource,
            DownloadRequest::create(),
            $responseAction,
            self::$etag
        );
    }

    private function extractBoundary(?string $contentType): string
    {
        self::assertNotNull($contentType);

        self::assertMatchesRegularExpression(
            '/^multipart\/byteranges; boundary=([a-f0-9]{32})$/',
            $contentType
        );

        preg_match('/boundary=([a-f0-9]{32})/', $contentType, $matches);

        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    /**
     * @param list<array{0:int, 1:int, 2:string}> $parts
     */
    private function buildExpectedMultipartBody(string $boundary, string $mime, int $filesize, array $parts): string
    {
        $body = '';

        foreach ($parts as [$start, $end, $data]) {
            $body .= sprintf(
                "--%s\r\n" .
                "Content-Type: %s\r\n" .
                "Content-Range: bytes %d-%d/%d\r\n" .
                "\r\n" .
                "%s\r\n",
                $boundary,
                $mime,
                $start,
                $end,
                $filesize,
                $data
            );
        }

        return $body . sprintf("--%s--\r\n", $boundary);
    }
}
