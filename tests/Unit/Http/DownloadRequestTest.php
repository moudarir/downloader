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
        parent::setUp();

        $this->server = $_SERVER;
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    public function testItCreatesGetRequestByDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = DownloadRequest::create();

        self::assertSame(
            RequestMethod::GET,
            $request->getMethod()
        );

        self::assertTrue($request->isGet());
        self::assertFalse($request->isHead());
        self::assertTrue($request->isSafeMethod());
    }

    public function testItCreatesHeadRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $request = DownloadRequest::create();

        self::assertSame(
            RequestMethod::HEAD,
            $request->getMethod()
        );

        self::assertFalse($request->isGet());
        self::assertTrue($request->isHead());
        self::assertTrue($request->isSafeMethod());
    }

    public function testItUsesGetWhenRequestMethodIsMissing(): void
    {
        $request = DownloadRequest::create();

        self::assertSame(
            RequestMethod::GET,
            $request->getMethod()
        );

        self::assertTrue($request->isGet());
        self::assertFalse($request->isHead());
        self::assertTrue($request->isSafeMethod());
    }

    public function testItReadsRangeHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_RANGE'] = 'bytes=0-1023';

        $request = DownloadRequest::create();

        self::assertSame(
            'bytes=0-1023',
            $request->getRange()
        );
    }

    public function testItReadsIfRangeHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_RANGE'] = '"etag-value"';

        $request = DownloadRequest::create();

        self::assertSame(
            '"etag-value"',
            $request->getIfRange()
        );
    }

    public function testItReadsIfMatchHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MATCH'] = '"etag-value"';

        $request = DownloadRequest::create();

        self::assertSame(
            '"etag-value"',
            $request->getIfMatch()
        );
    }

    public function testItReadsIfNoneMatchHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-value"';

        $request = DownloadRequest::create();

        self::assertSame(
            '"etag-value"',
            $request->getIfNoneMatch()
        );
    }

    public function testItParsesIfModifiedSince(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] =
            'Sun, 09 Aug 2026 11:17:59 GMT';

        $request = DownloadRequest::create();

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            $request->getIfModifiedSince()
        );
    }

    public function testItParsesIfUnmodifiedSince(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] =
            'Sun, 09 Aug 2026 11:17:59 GMT';

        $request = DownloadRequest::create();

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            $request->getIfUnmodifiedSince()
        );
    }

    public function testMissingHeadersAreReturnedAsNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = DownloadRequest::create();

        self::assertNull($request->getRange());
        self::assertNull($request->getIfRange());
        self::assertNull($request->getIfMatch());
        self::assertNull($request->getIfNoneMatch());
        self::assertNull($request->getIfModifiedSince());
        self::assertNull($request->getIfUnmodifiedSince());
    }

    public function testEmptyHeadersAreReturnedAsNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_RANGE'] = '   ';
        $_SERVER['HTTP_IF_RANGE'] = '';
        $_SERVER['HTTP_IF_MATCH'] = ' ';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = ' ';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = '';

        $request = DownloadRequest::create();

        self::assertNull($request->getRange());
        self::assertNull($request->getIfRange());
        self::assertNull($request->getIfMatch());
        self::assertNull($request->getIfNoneMatch());
        self::assertNull($request->getIfModifiedSince());
        self::assertNull($request->getIfUnmodifiedSince());
    }

    public function testInvalidHttpDatesAreReturnedAsNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'invalid-date';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = 'invalid-date';

        $request = DownloadRequest::create();

        self::assertNull($request->getIfModifiedSince());
        self::assertNull($request->getIfUnmodifiedSince());
    }

    public function testItRejectsUnsupportedRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'The HTTP request method `POST` is not supported.'
        );

        DownloadRequest::create();
    }

    public function testItRejectsPutRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'The HTTP request method `PUT` is not supported.'
        );

        DownloadRequest::create();
    }

    public function testRequestMethodIsCaseInsensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'head';

        $request = DownloadRequest::create();

        self::assertSame(
            RequestMethod::HEAD,
            $request->getMethod()
        );

        self::assertTrue($request->isHead());
        self::assertTrue($request->isSafeMethod());
    }

    public function testItReadsAllSupportedHeadersTogether(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_RANGE'] = 'bytes=0-99';
        $_SERVER['HTTP_IF_RANGE'] = '"etag-range"';
        $_SERVER['HTTP_IF_MATCH'] = '"etag-match"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-none"';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] =
            'Sun, 09 Aug 2026 11:17:59 GMT';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] =
            'Mon, 10 Aug 2026 11:17:59 GMT';

        $request = DownloadRequest::create();

        self::assertSame(
            RequestMethod::GET,
            $request->getMethod()
        );

        self::assertTrue($request->isGet());
        self::assertFalse($request->isHead());
        self::assertTrue($request->isSafeMethod());

        self::assertSame(
            'bytes=0-99',
            $request->getRange()
        );

        self::assertSame(
            '"etag-range"',
            $request->getIfRange()
        );

        self::assertSame(
            '"etag-match"',
            $request->getIfMatch()
        );

        self::assertSame(
            '"etag-none"',
            $request->getIfNoneMatch()
        );

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            $request->getIfModifiedSince()
        );

        self::assertSame(
            strtotime('Mon, 10 Aug 2026 11:17:59 GMT'),
            $request->getIfUnmodifiedSince()
        );
    }

    public function testSafeMethodMeansGetOrHeadOnly(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $getRequest = DownloadRequest::create();

        self::assertTrue($getRequest->isSafeMethod());

        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $headRequest = DownloadRequest::create();

        self::assertTrue($headRequest->isSafeMethod());
    }
}
