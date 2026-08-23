<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Integration\Http;

use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use Moudarir\Downloader\Tests\Support\TestConfig;
use Moudarir\Downloader\Tests\Support\TestHttp;
use PHPUnit\Framework\TestCase;

final class DownloadFileTest extends TestCase
{

    private static string $url;

    private static FixtureFile $resource;

    public static function setUpBeforeClass(): void
    {
        self::$url = TestConfig::url('file');
        self::$resource = FixtureFile::create('mov');
    }

    public function testGetReturnsExpectedHeaders(): void
    {
        $response = TestHttp::request(self::$url);

        self::assertArrayHasKey('etag', $response['headers']);
        self::assertArrayHasKey('last-modified', $response['headers']);
        self::assertArrayHasKey('content-length', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                self::$resource->contentDisposition(),
                self::$resource->getMime(),
                'private, must-revalidate',
            ],
            [
                $response['status'],
                $response['headers']['content-disposition'] ?? null,
                $response['headers']['content-type'] ?? null,
                $response['headers']['cache-control'] ?? null,
            ]
        );
    }

    public function testHeadReturnsExpectedHeaders(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD');

        self::assertArrayHasKey('etag', $response['headers']);
        self::assertArrayHasKey('last-modified', $response['headers']);
        self::assertArrayHasKey('content-length', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                self::$resource->contentDisposition(),
                self::$resource->getMime(),
                'private, must-revalidate',
                '',
            ],
            [
                $response['status'],
                $response['headers']['content-disposition'] ?? null,
                $response['headers']['content-type'] ?? null,
                $response['headers']['cache-control'] ?? null,
                $response['body'],
            ]
        );
    }

    public function testPostIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, 'POST');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testPutIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, 'PUT');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testDeleteIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, 'DELETE');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testIfNoneMatchMatchingEtagReturnsNotModified(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');

        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = TestHttp::request(self::$url, headers: ['If-None-Match' => $etag]);

        self::assertSame(
            [StatusCode::NOT_MODIFIED->value, ''],
            [$response['status'], $response['body']]
        );
    }

    public function testIfNoneMatchWithDifferentEtagReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, headers: ['If-None-Match' => '"etag-inexistant"']);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfNoneMatchWildcardReturnsNotModified(): void
    {
        $response = TestHttp::request(self::$url, headers: ['If-None-Match' => '*']);

        self::assertSame(
            [StatusCode::NOT_MODIFIED->value, ''],
            [$response['status'], $response['body']]
        );
    }

    public function testIfModifiedSinceMatchingLastModifiedReturnsNotModified(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = TestHttp::request(self::$url, headers: ['If-Modified-Since' => $lastModified]);

        self::assertSame(
            [StatusCode::NOT_MODIFIED->value, ''],
            [$response['status'], $response['body']]
        );
    }

    public function testIfModifiedSinceOlderThanLastModifiedReturnsOk(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $olderDate = gmdate('D, d M Y H:i:s', $timestamp - 86400) . ' GMT';
        $response = TestHttp::request(self::$url, headers: ['If-Modified-Since' => $olderDate]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfModifiedSinceNewerThanLastModifiedReturnsNotModified(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $newerDate = gmdate('D, d M Y H:i:s', $timestamp + 86400) . ' GMT';
        $response = TestHttp::request(self::$url, headers: ['If-Modified-Since' => $newerDate]);

        self::assertSame(
            [StatusCode::NOT_MODIFIED->value, ''],
            [$response['status'], $response['body']]
        );
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = TestHttp::request(self::$url, headers: [
            'If-None-Match' => $etag,
            'If-Modified-Since' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
    }

    public function testIfNoneMatchDifferentEtagIgnoresIfModifiedSince(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = TestHttp::request(self::$url, headers: [
            'If-None-Match' => '"etag-inexistant"',
            'If-Modified-Since' => $lastModified,
        ]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfMatchMatchingEtagReturnsOk(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = TestHttp::request(self::$url, headers: ['If-Match' => $etag]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfMatchDifferentEtagReturnsPreconditionFailed(): void
    {
        $response = TestHttp::request(self::$url, headers: ['If-Match' => '"etag-inexistant"']);

        self::assertSame(
            [StatusCode::PRECONDITION_FAILED->value, '0'],
            [$response['status'], $response['headers']['content-length'] ?? null]
        );
    }

    public function testIfMatchWildcardReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, headers: ['If-Match' => '*']);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfUnmodifiedSinceMatchingLastModifiedReturnsOk(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = TestHttp::request(self::$url, headers: ['If-Unmodified-Since' => $lastModified]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfUnmodifiedSinceOlderThanLastModifiedReturnsPreconditionFailed(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $olderDate = gmdate('D, d M Y H:i:s', $timestamp - 86400) . ' GMT';
        $response = TestHttp::request(self::$url, headers: ['If-Unmodified-Since' => $olderDate]);

        self::assertSame(StatusCode::PRECONDITION_FAILED->value, $response['status']);
    }

    public function testIfUnmodifiedSinceNewerThanLastModifiedReturnsOk(): void
    {
        $response = TestHttp::request(self::$url, headers: [
            'If-Unmodified-Since' => 'Thu, 01 Jan 2027 00:00:00 GMT'
        ]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }
}
