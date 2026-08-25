<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Http\DownloadHeaders;
use PHPUnit\Framework\TestCase;

final class DownloadHeadersTest extends TestCase
{

    public function testItStartsWithNoHeaders(): void
    {
        $headers = new DownloadHeaders();

        self::assertSame([], $headers->all());
    }

    public function testItNormalizesHeaderName(): void
    {
        $headers = new DownloadHeaders();

        $headers->addHeader('content-type', 'text/plain');

        self::assertArrayHasKey('Content-Type', $headers->all());
        self::assertSame('text/plain', $headers->all()['Content-Type']);
    }

    public function testItReplacesAnExistingHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers
            ->addHeader('Content-Type', 'text/plain')
            ->addHeader('Content-Type', 'text/html');

        self::assertSame(['Content-Type' => 'text/html'], $headers->all());
    }

    public function testItSetsAttachmentDispositionByDefault(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader('video.mp4');

        self::assertSame(
            'attachment; filename="video.mp4"; filename*=UTF-8\'\'video.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItSetsInlineDisposition(): void
    {
        $headers = new DownloadHeaders();

        $result = $headers->setDisposition(ContentDisposition::INLINE);

        self::assertSame($headers, $result);

        $headers->addContentDispositionHeader('video.mp4');

        self::assertSame(
            'inline; filename="video.mp4"; filename*=UTF-8\'\'video.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItAddsContentLengthHeader(): void
    {
        $headers = new DownloadHeaders();

        $result = $headers->addContentLengthHeader(1024);

        self::assertSame($headers, $result);
        self::assertSame('1024', $headers->all()['Content-Length']);
    }

    public function testItAddsAcceptRangesHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers->addAcceptRangesHeader();

        self::assertSame('bytes', $headers->all()['Accept-Ranges']);
    }

    public function testItAddsContentRangeHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentRangeHeader('bytes 0-99/1000');

        self::assertSame('bytes 0-99/1000', $headers->all()['Content-Range']);
    }

    public function testItAddsContentTypeHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentTypeHeader('video/mp4');

        self::assertSame('video/mp4', $headers->all()['Content-Type']);
    }

    public function testItAddsLastModifiedHeader(): void
    {
        $headers = new DownloadHeaders();

        $timestamp = strtotime('Sun, 09 Aug 2026 11:17:59 GMT');

        self::assertNotFalse($timestamp);

        $headers->addLastModifiedHeader($timestamp);

        self::assertSame('Sun, 09 Aug 2026 11:17:59 GMT', $headers->all()['Last-Modified']);
    }

    public function testItDoesNotAddLastModifiedHeaderWhenValueIsNull(): void
    {
        $headers = new DownloadHeaders();

        $headers->addLastModifiedHeader();

        self::assertArrayNotHasKey('Last-Modified', $headers->all());
    }

    public function testItAddsETagHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers->addETagHeader('"abc123"');

        self::assertSame('"abc123"', $headers->all()['ETag']);
    }

    public function testItDoesNotAddETagHeaderWhenValueIsNull(): void
    {
        $headers = new DownloadHeaders();

        $headers->addETagHeader();

        self::assertArrayNotHasKey('ETag', $headers->all());
    }

    public function testItAddsDefaultCacheControl(): void
    {
        $headers = new DownloadHeaders();

        $headers->applyDefaultHeaders();

        self::assertSame(DownloadConfig::DEFAULT_CACHE_CONTROL, $headers->all()['Cache-Control']);
    }

    public function testItDoesNotOverrideCustomCacheControl(): void
    {
        $headers = new DownloadHeaders();

        $headers->addHeader('Cache-Control', 'public, max-age=3600');

        $headers->applyDefaultHeaders();

        self::assertSame('public, max-age=3600', $headers->all()['Cache-Control']);
    }

    public function testItCanApplyDefaultCacheControlMultipleTimes(): void
    {
        $headers = new DownloadHeaders();

        $headers->applyDefaultHeaders();
        $headers->applyDefaultHeaders();

        self::assertSame(
            ['Cache-Control' => DownloadConfig::DEFAULT_CACHE_CONTROL],
            $headers->all()
        );
    }

    public function testItReturnsAllHeaders(): void
    {
        $headers = new DownloadHeaders();

        $headers
            ->addContentTypeHeader('video/mp4')
            ->addContentLengthHeader(1234)
            ->addAcceptRangesHeader()
            ->addETagHeader('"etag"');

        self::assertSame(
            [
                'Content-Type' => 'video/mp4',
                'Content-Length' => '1234',
                'Accept-Ranges' => 'bytes',
                'ETag' => '"etag"',
            ],
            $headers->all()
        );
    }
}
