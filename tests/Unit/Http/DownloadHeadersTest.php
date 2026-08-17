<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadHeaders;
use PHPUnit\Framework\TestCase;

final class DownloadHeadersTest extends TestCase
{

    public function testItStartsWithNoHeaders(): void
    {
        $headers = new DownloadHeaders();

        self::assertSame([], $headers->all());
    }

    public function testItAddsCustomStringHeader(): void
    {
        $headers = new DownloadHeaders();

        $result = $headers->addHeader('Content-Type', 'video/mp4');

        self::assertSame($headers, $result);
        self::assertSame(['Content-Type' => 'video/mp4'], $headers->all());
    }

    public function testItAddsCustomIntegerHeader(): void
    {
        $headers = new DownloadHeaders();

        $headers->addHeader('Content-Length', 12345);

        self::assertSame('12345', $headers->all()['Content-Length']);
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

    public function testItRejectsInvalidHeaderName(): void
    {
        $headers = new DownloadHeaders();

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("Invalid HTTP header name");

        $headers->addHeader('Invalid Header', 'value');
    }

    public function testItRejectsHeaderInjectionInValue(): void
    {
        $headers = new DownloadHeaders();

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("Invalid HTTP header value");

        $headers->addHeader('Content-Type', "text/plain\r\nX-Injected: true");
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

    public function testItEncodesUtf8FilenameInExtendedParameter(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader('vidéo été.mp4');

        self::assertSame(
            'attachment; filename="video ete.mp4"; filename*=UTF-8\'\'vid%C3%A9o%20%C3%A9t%C3%A9.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItSanitizesControlCharactersFromFilename(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader("video\r\nname.mp4");

        self::assertSame(
            'attachment; filename="videoname.mp4"; filename*=UTF-8\'\'video%0D%0Aname.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItEscapesQuotesAndBackslashesInFilename(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader('video "test"\\sample.mp4');

        self::assertSame(
            'attachment; filename="video \\"test\\"\\\\sample.mp4"; filename*=UTF-8\'\'video%20%22test%22%5Csample.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItUsesDownloadAsFallbackWhenFilenameHasNoAsciiBasename(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader('文件.mp4');

        self::assertSame(
            'attachment; filename="download.mp4"; filename*=UTF-8\'\'%E6%96%87%E4%BB%B6.mp4',
            $headers->all()['Content-Disposition']
        );
    }

    public function testItSanitizesFilenameExtension(): void
    {
        $headers = new DownloadHeaders();

        $headers->addContentDispositionHeader('video.test-1.mp4');

        self::assertSame(
            'attachment; filename="video.test-1.mp4"; filename*=UTF-8\'\'video.test-1.mp4',
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

        $headers->addContentType('video/mp4');

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
            ->addContentType('video/mp4')
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
