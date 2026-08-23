<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Helpers;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ContentDisposition;
use Moudarir\Downloader\Helpers\FileHelper;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use PHPUnit\Framework\TestCase;

final class FileHelperTest extends TestCase
{

    public function testItEncodesUtf8FilenameInExtendedParameter(): void
    {
        self::assertSame(
            'attachment; filename="video ete.mp4"; filename*=UTF-8\'\'vid%C3%A9o%20%C3%A9t%C3%A9.mp4',
            FileHelper::formatContentDisposition('vidéo été.mp4')
        );
    }

    public function testItSanitizesControlCharactersFromFilename(): void
    {
        self::assertSame(
            'attachment; filename="videoname.mp4"; filename*=UTF-8\'\'video%0D%0Aname.mp4',
            FileHelper::formatContentDisposition("video\r\nname.mp4")
        );
    }

    public function testItEscapesQuotesAndBackslashesInFilename(): void
    {
        self::assertSame(
            'attachment; filename="video \\"test\\"\\\\sample.mp4"; filename*=UTF-8\'\'video%20%22test%22%5Csample.mp4',
            FileHelper::formatContentDisposition('video "test"\\sample.mp4')
        );
    }

    public function testItUsesDownloadAsFallbackWhenFilenameHasNoAsciiBasename(): void
    {
        self::assertSame(
            'attachment; filename="download.mp4"; filename*=UTF-8\'\'%E6%96%87%E4%BB%B6.mp4',
            FileHelper::formatContentDisposition('文件.mp4')
        );
    }

    public function testItSanitizesFilenameExtension(): void
    {
        self::assertSame(
            'attachment; filename="video.test-1.mp4"; filename*=UTF-8\'\'video.test-1.mp4',
            FileHelper::formatContentDisposition('video.test-1.mp4')
        );
    }

    public function testItSetsInlineDisposition(): void
    {
        self::assertSame(
            'inline; filename="video.mp4"; filename*=UTF-8\'\'video.mp4',
            FileHelper::formatContentDisposition('video.mp4', ContentDisposition::INLINE)
        );
    }

    public function testItDetectsMimeTypeIfMimeIsTrue(): void
    {
        $resource = FixtureFile::create('pdf');
        self::assertSame(
            'application/pdf',
            FileHelper::detectMimeType($resource->getFilepath(), true)
        );
    }

    public function testItReturnsDefinedMime(): void
    {
        self::assertSame(
            'video/mp4',
            FileHelper::detectMimeType('/path/to/video.mp4', 'video/mp4')
        );
    }

    public function testItReturnsDefaultMimeType(): void
    {
        self::assertSame(
            [
                DownloadConfig::DEFAULT_MIME,
                DownloadConfig::DEFAULT_MIME,
            ],
            [
                FileHelper::detectMimeType('/path/to/video.mp4', ''),
                FileHelper::detectMimeType('/path/to/video.mp4', '   '),
            ]
        );
    }
}
