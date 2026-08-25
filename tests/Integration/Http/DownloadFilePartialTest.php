<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Integration\Http;

use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use Moudarir\Downloader\Tests\Support\TestConfig;
use Moudarir\Downloader\Tests\Support\TestHttp;
use PHPUnit\Framework\TestCase;

final class DownloadFilePartialTest extends TestCase
{

    private static string $url;

    private static FixtureFile $resource;

    public static function setUpBeforeClass(): void
    {
        self::$url = TestConfig::url('file-partial');
        self::$resource = FixtureFile::create('mov');
    }

    public function testWithoutRangeReturnsCompleteResponse(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD');

        self::assertArrayHasKey('content-length', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                self::$resource->contentDisposition(ContentDisposition::INLINE),
                self::$resource->getMime(),
                'bytes',
            ],
            [
                $response['status'],
                $response['headers']['content-disposition'] ?? null,
                $response['headers']['content-type'] ?? null,
                $response['headers']['accept-ranges'] ?? null,
            ]
        );
    }

    public function testItReturnsSingleRange(): void
    {
        $response = TestHttp::request(self::$url, 'GET', ['Range' => 'bytes=0-9'], true);

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                'bytes 0-9/'.self::$resource->getFilesize(),
                self::$resource->getMime(),
                '10',
                10,
            ],
            [
                $response['status'],
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-type'] ?? null,
                $response['headers']['content-length'] ?? null,
                strlen($response['body']),
            ]
        );
    }

    public function testItReturnsMultipartResponseForMultipleRanges(): void
    {
        $response = TestHttp::request(self::$url, 'GET', ['Range' => 'bytes=0-9,20-29'], true);
        $contentType = $response['headers']['content-type'] ?? null;
        $filesize = self::$resource->getFilesize();

        self::assertNotNull($contentType);
        self::assertMatchesRegularExpression(
            '/^multipart\/byteranges; boundary=[a-f0-9]{32}$/',
            $contentType
        );
        self::assertStringContainsString("Content-Range: bytes 0-9/$filesize\r\n", $response['body']);
        self::assertStringContainsString("Content-Range: bytes 20-29/$filesize\r\n", $response['body']);
        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                '270',
                270,
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
                strlen($response['body']),
            ]
        );
    }

    public function testItReturns416ForUnsatisfiableRange(): void
    {
        $response = TestHttp::request(self::$url, 'GET', ['Range' => 'bytes=999999999-'], true);

        self::assertSame(
            [
                StatusCode::RANGE_NOT_SATISFIABLE->value,
                'bytes */'.self::$resource->getFilesize(),
                '0',
                '',
            ],
            [
                $response['status'],
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-length'] ?? null,
                $response['body'],
            ]
        );
    }

    public function testInvalidRangeFallsBackToCompleteResponse(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD', ['Range' => 'bytes=invalid']);

        self::assertArrayNotHasKey('content-range', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                (string)self::$resource->getFilesize(),
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testIfRangeMatchingEtagReturnsPartialResponse(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => $etag
        ]);

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                'bytes 0-9/'.self::$resource->getFilesize(),
                '10',
            ],
            [
                $response['status'],
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testIfRangeWithDifferentEtagFallsBackToCompleteResponse(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => '"etag-inexistant"'
        ]);

        self::assertArrayNotHasKey('content-range', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                (string)self::$resource->getFilesize(),
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testWeakIfRangeEtagFallsBackToCompleteResponse(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => 'W/' . $etag
        ]);

        self::assertArrayNotHasKey('content-range', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                (string)self::$resource->getFilesize(),
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testIfRangeMatchingDateReturnsPartialResponse(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => $lastModified
        ]);

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                'bytes 0-9/'.self::$resource->getFilesize(),
                '10',
            ],
            [
                $response['status'],
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testIfRangeWithDifferentDateFallsBackToCompleteResponse(): void
    {
        $head = TestHttp::request(self::$url, 'HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => gmdate('D, d M Y H:i:s', $timestamp - 86400) . ' GMT',
        ]);

        self::assertArrayNotHasKey('content-range', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                (string)self::$resource->getFilesize(),
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testIfRangeWithInvalidDateFallsBackToCompleteResponse(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD', [
            'Range' => 'bytes=0-9',
            'If-Range' => 'invalid-date'
        ]);

        self::assertArrayNotHasKey('content-range', $response['headers']);
        self::assertSame(
            [
                StatusCode::OK->value,
                (string)self::$resource->getFilesize(),
            ],
            [
                $response['status'],
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testMultipleRangesThatOverlapAreMerged(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD', ['Range' => 'bytes=0-9,5-19']);

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                self::$resource->getMime(),
                'bytes 0-19/'.self::$resource->getFilesize(),
                '20',
            ],
            [
                $response['status'],
                $response['headers']['content-type'] ?? null,
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-length'] ?? null,
            ]
        );
    }

    public function testMultipleRangesThatAreAdjacentAreMerged(): void
    {
        $response = TestHttp::request(self::$url, 'HEAD', ['Range' => 'bytes=0-9,10-19']);

        self::assertSame(
            [
                StatusCode::PARTIAL_CONTENT->value,
                'bytes 0-19/'.self::$resource->getFilesize(),
                '20',
            ],
            [
                $response['status'],
                $response['headers']['content-range'] ?? null,
                $response['headers']['content-length'] ?? null,
            ]
        );
    }
}
