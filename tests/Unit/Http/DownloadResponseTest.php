<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
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
        $response = $this->createResponse();

        $metadata = $response->metadata();

        self::assertSame(
            200,
            $metadata->statusCode()
        );

        self::assertSame(
            13,
            $metadata->contentLength()
        );

        self::assertSame(
            'text/plain',
            $metadata->contentType()
        );

        self::assertSame(
            ResponseAction::DEFAULT,
            $metadata->responseAction()
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
    }

    public function testItExposesResourceMetadata(): void
    {
        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain'
        );

        $metadata = $response->metadata();

        self::assertNull(
            $metadata->filepath()
        );

        self::assertSame(
            'hello.txt',
            $metadata->filename()
        );

        self::assertSame(
            13,
            $metadata->filesize()
        );

        self::assertSame(
            'text/plain',
            $metadata->mimeType()
        );

        self::assertNull(
            $metadata->lastModified()
        );

        self::assertFalse(
            $metadata->etagIsWeak()
        );

        self::assertNotSame(
            '',
            $metadata->etagValue()
        );
    }

    public function testItCreatesPartialResponseForSingleRange(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(
            206,
            $metadata->statusCode()
        );

        self::assertSame(
            5,
            $metadata->contentLength()
        );

        self::assertSame(
            'text/plain',
            $metadata->contentType()
        );

        self::assertSame(
            ResponseAction::PARTIAL,
            $metadata->responseAction()
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

        self::assertSame(
            [
                0,
                4,
                5,
            ],
            [
                $metadata->rangeItems()[0]->getStart(),
                $metadata->rangeItems()[0]->getEnd(),
                $metadata->rangeItems()[0]->getLength(),
            ]
        );
    }

    public function testItCreatesMultipartResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4,8-12';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(
            206,
            $metadata->statusCode()
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

        self::assertCount(
            2,
            $metadata->rangeItems()
        );

        self::assertNotNull(
            $metadata->rangeItems()
        );

        self::assertSame(
            0,
            $metadata->rangeItems()[0]->getStart()
        );

        self::assertSame(
            4,
            $metadata->rangeItems()[0]->getEnd()
        );

        self::assertSame(
            8,
            $metadata->rangeItems()[1]->getStart()
        );

        self::assertSame(
            12,
            $metadata->rangeItems()[1]->getEnd()
        );

        self::assertStringStartsWith(
            'multipart/byteranges; boundary=',
            $metadata->contentType()
        );

        self::assertGreaterThan(
            0,
            $metadata->contentLength()
        );
    }

    public function testContentLengthForMultipartResponseMatchesMultipartStructure(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4,8-12';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(
            206,
            $metadata->statusCode()
        );

        self::assertSame(
            strlen(
                $this->buildExpectedMultipartBody(
                    boundary: $this->extractBoundary(
                        $metadata->contentType()
                    ),
                    mime: 'text/plain',
                    filesize: 13,
                    parts: [
                        [0, 4, 'Hello'],
                        [8, 12, 'orld!'],
                    ],
                )
            ),
            $metadata->contentLength()
        );
    }

    public function testInvalidRangeFallsBackToFullResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=abc-def';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(
            200,
            $metadata->statusCode()
        );

        self::assertSame(
            13,
            $metadata->contentLength()
        );

        self::assertSame(
            'text/plain',
            $metadata->contentType()
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
    }

    public function testUnsatisfiableRangeCreates416Response(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=100-';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(
            416,
            $metadata->statusCode()
        );

        self::assertSame(
            0,
            $metadata->contentLength()
        );

        self::assertNull(
            $metadata->contentType()
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
    }

    public function testPreconditionResponseHasNoContent(): void
    {
        $resource = DownloadData::create(
            'Hello, World!',
            'hello.txt',
            'text/plain'
        );

        $request = DownloadRequest::create();

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );

        $headers = new DownloadHeaders();

        $response = DownloadResponse::precondition(
            304,
            $headers,
            $resource,
            $request,
            ResponseAction::DEFAULT,
            $etag
        );

        $metadata = $response->metadata();

        self::assertSame(
            304,
            $metadata->statusCode()
        );

        self::assertSame(
            0,
            $metadata->contentLength()
        );

        self::assertNull(
            $metadata->contentType()
        );

        self::assertSame(
            ResponseAction::DEFAULT,
            $metadata->responseAction()
        );
    }

    public function testPreconditionFailedResponseHasNoContent(): void
    {
        $resource = DownloadData::create(
            'Hello, World!',
            'hello.txt',
            'text/plain'
        );

        $request = DownloadRequest::create();

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );

        $headers = new DownloadHeaders();

        $response = DownloadResponse::precondition(
            412,
            $headers,
            $resource,
            $request,
            ResponseAction::DEFAULT,
            $etag
        );

        $metadata = $response->metadata();

        self::assertSame(
            412,
            $metadata->statusCode()
        );

        self::assertSame(
            0,
            $metadata->contentLength()
        );

        self::assertNull(
            $metadata->contentType()
        );
    }

    public function testMetadataContentLengthIsAlreadyKnownBeforeSend(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=2-6';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        self::assertSame(
            5,
            $response->metadata()->contentLength()
        );
    }

    public function testMetadataReturnsRangeItemsFromResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-2,8-10';

        $response = $this->createResponse(
            data: 'Hello, World!',
            filename: 'hello.txt',
            mime: 'text/plain',
            responseAction: ResponseAction::PARTIAL
        );

        $items = $response->metadata()->rangeItems();

        self::assertNotNull($items);
        self::assertCount(2, $items);

        self::assertInstanceOf(
            DownloadRangeItem::class,
            $items[0]
        );

        self::assertInstanceOf(
            DownloadRangeItem::class,
            $items[1]
        );
    }

    public function testInlineReturnsTheSameResponse(): void
    {
        $response = $this->createResponse();

        self::assertSame(
            $response,
            $response->inline()
        );
    }

    public function testAddCacheControlReturnsTheSameResponse(): void
    {
        $response = $this->createResponse();

        self::assertSame(
            $response,
            $response->addCacheControl(
                'public, max-age=3600'
            )
        );
    }

    public function testMetadataRetainsTheConfiguredResponseAction(): void
    {
        $response = $this->createResponse(
            responseAction: ResponseAction::PARTIAL
        );

        self::assertSame(
            ResponseAction::PARTIAL,
            $response->metadata()->responseAction()
        );
    }

    private function createResponse(
        string $data = 'Hello, World!',
        string $filename = 'hello.txt',
        ?string $mime = 'text/plain',
        ResponseAction $responseAction = ResponseAction::DEFAULT,
    ): DownloadResponse {
        $resource = DownloadData::create(
            $data,
            $filename,
            $mime
        );

        $headers = new DownloadHeaders();

        $request = DownloadRequest::create();

        $etag = DownloadETag::create(
            $resource,
            ETagStrategy::MD5
        );

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

        return $body . sprintf(
                "--%s--\r\n",
                $boundary
            );
    }
}