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
use Moudarir\Downloader\Resources\DownloadData;
use PHPUnit\Framework\TestCase;

final class DownloadResponseTest extends TestCase
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

    public function testItCreatesDefaultResponseMetadata(): void
    {
        $response = $this->createResponse(ResponseAction::DEFAULT);

        $metadata = $response->metadata();

        self::assertSame(StatusCode::OK, $metadata->statusCode());
        self::assertSame(ResponseAction::DEFAULT, $metadata->responseAction());
        self::assertSame(13, $metadata->contentLength());
        self::assertSame('text/plain', $metadata->contentType());
        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
    }

    public function testItExposesResourceMetadata(): void
    {
        $response = $this->createResponse(ResponseAction::DEFAULT);
        $metadata = $response->metadata();

        self::assertNull($metadata->filepath());
        self::assertNull($metadata->lastModified());
        self::assertSame('hello.txt', $metadata->filename());
        self::assertSame(13, $metadata->filesize());
        self::assertSame('text/plain', $metadata->mimeType());
        self::assertNotSame('', $metadata->etagValue());
        self::assertFalse($metadata->etagIsWeak());
    }

    public function testItCreatesPartialResponseForSingleRange(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4';

        $response = $this->createResponse();
        $metadata = $response->metadata();
        $rangeItem = $metadata->rangeItems()[0];

        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertSame(ResponseAction::PARTIAL, $metadata->responseAction());
        self::assertSame(5, $metadata->contentLength());
        self::assertSame('text/plain', $metadata->contentType());
        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
        self::assertSame(
            [0, 4, 5],
            [$rangeItem->getStart(), $rangeItem->getEnd(), $rangeItem->getLength()]
        );
    }

    public function testItCreatesMultipartResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4,8-12';

        $response = $this->createResponse();

        $metadata = $response->metadata();
        $rangeItems = $metadata->rangeItems();
        $firstRangeItem = $rangeItems[0];
        $secondRangeItem = $rangeItems[1];

        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
        self::assertTrue($metadata->rangeIsMultipart());
        self::assertCount(2, $rangeItems);
        self::assertNotNull($rangeItems);
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

        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertSame(
            strlen(
                $this->buildExpectedMultipartBody(
                    boundary: $this->extractBoundary($metadata->contentType()),
                    mime: 'text/plain',
                    filesize: 13,
                    parts: [[0, 4, 'Hello'], [8, 12, 'orld!']],
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
        self::assertSame(13, $metadata->contentLength());
        self::assertSame('text/plain', $metadata->contentType());
        self::assertFalse($metadata->hasRange());
        self::assertFalse($metadata->rangeIsPartial());
        self::assertFalse($metadata->rangeIsMultipart());
    }

    public function testUnsatisfiableRangeCreates416Response(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=100-';

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
        $resource = DownloadData::create('Hello, World!', 'hello.txt', 'text/plain');
        $request = DownloadRequest::create();
        $etag = DownloadETag::create($resource, ETagStrategy::MD5);
        $headers = new DownloadHeaders();
        $response = DownloadResponse::precondition(
            StatusCode::NOT_MODIFIED,
            $headers,
            $resource,
            $request,
            ResponseAction::DEFAULT,
            $etag
        );

        $metadata = $response->metadata();

        self::assertSame(StatusCode::NOT_MODIFIED, $metadata->statusCode());
        self::assertSame(ResponseAction::DEFAULT, $metadata->responseAction());
        self::assertSame(0, $metadata->contentLength());
        self::assertNull($metadata->contentType());
    }

    public function testPreconditionFailedResponseHasNoContent(): void
    {
        $resource = DownloadData::create('Hello, World!', 'hello.txt', 'text/plain');
        $request = DownloadRequest::create();
        $etag = DownloadETag::create($resource, ETagStrategy::MD5);
        $headers = new DownloadHeaders();
        $response = DownloadResponse::precondition(
            StatusCode::PRECONDITION_FAILED,
            $headers,
            $resource,
            $request,
            ResponseAction::DEFAULT,
            $etag
        );

        $metadata = $response->metadata();

        self::assertSame(StatusCode::PRECONDITION_FAILED, $metadata->statusCode());
        self::assertSame(0, $metadata->contentLength());
        self::assertNull($metadata->contentType());
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

    public function testInlineReturnsTheSameResponse(): void
    {
        $response = $this->createResponse(ResponseAction::DEFAULT);

        self::assertSame($response, $response->inline());
    }

    public function testAddCacheControlReturnsTheSameResponse(): void
    {
        $response = $this->createResponse(ResponseAction::DEFAULT);

        self::assertSame($response, $response->addCacheControl('public, max-age=3600'));
    }

    public function testMetadataRetainsTheConfiguredResponseAction(): void
    {
        $response = $this->createResponse();

        self::assertSame(ResponseAction::PARTIAL, $response->metadata()->responseAction());
    }

    private function createResponse(ResponseAction $responseAction = ResponseAction::PARTIAL): DownloadResponse
    {
        $resource = DownloadData::create('Hello, World!', 'hello.txt', 'text/plain');
        $request = DownloadRequest::create();
        $etag = DownloadETag::create($resource, ETagStrategy::MD5);
        $headers = new DownloadHeaders();

        return DownloadResponse::create(
            $headers,
            $resource,
            $request,
            $responseAction,
            $etag
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
