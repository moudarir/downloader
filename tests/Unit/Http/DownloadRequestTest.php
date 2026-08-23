<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\RequestMethod;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadRequest;
use PHPUnit\Framework\TestCase;

final class DownloadRequestTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private array $server;

    protected function setUp(): void
    {
       $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testItCreatesDefaultGetRequestFromEmptyServer(): void
    {
        $_SERVER = [];
        $request = DownloadRequest::create();

        self::assertSame(RequestMethod::GET, $request->getMethod());
        self::assertTrue($request->isGet());
        self::assertFalse($request->isHead());
        self::assertTrue($request->isSafeMethod());
        self::assertNull($request->getRange());
        self::assertNull($request->getIfRange());
        self::assertNull($request->getIfMatch());
        self::assertNull($request->getIfNoneMatch());
        self::assertNull($request->getIfModifiedSince());
        self::assertNull($request->getIfUnmodifiedSince());
    }

    public function testItSupportsHeadRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $request = DownloadRequest::create();

        self::assertSame(RequestMethod::HEAD, $request->getMethod());
        self::assertTrue($request->isHead());
        self::assertFalse($request->isGet());
        self::assertTrue($request->isSafeMethod());
    }

    public function testItThrowsOnUnsupportedMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The HTTP request method `POST` is not supported.");

        DownloadRequest::create();
    }

    public function testItParsesHeaderFieldsCorrectly(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_RANGE'] = 'bytes=0-499';
        $_SERVER['HTTP_IF_RANGE'] = '"123456789"';
        $_SERVER['HTTP_IF_MATCH'] = '"etag-match"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-none-match"';

        $request = DownloadRequest::create();

        self::assertSame(
            [
                'bytes=0-499',
                '"123456789"',
                '"etag-match"',
                '"etag-none-match"',
            ],
            [
                $request->getRange(),
                $request->getIfRange(),
                $request->getIfMatch(),
                $request->getIfNoneMatch(),
            ]
        );
    }

    public function testItConvertsEmptyStringHeadersToNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_RANGE'] = '   ';
        $_SERVER['HTTP_IF_RANGE'] = '';

        $request = DownloadRequest::create();

        self::assertNull($request->getRange());
        self::assertNull($request->getIfRange());
    }

    public function testItParsesDateHeadersWithDifferentFormats(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Wed, 21 Oct 2015 07:28:00 GMT'; // IMF-fixdate
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = 'Sun Nov  6 08:49:37 1994'; // asctime

        $request = DownloadRequest::create();

        self::assertSame(
            [1445412480, 784111777],
            [$request->getIfModifiedSince(), $request->getIfUnmodifiedSince()]
        );
    }

    public function testItReturnsNullForInvalidDateHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Invalid Date String';

        $request = DownloadRequest::create();

        self::assertNull($request->getIfModifiedSince());
    }
}
