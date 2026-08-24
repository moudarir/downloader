<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Http\DownloadMultipartResponse;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Tests\Support\FixtureData;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use Moudarir\Downloader\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class DownloadMultipartResponseTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private static array $multipart;

    private static string $boundary;

    private static DownloadConfig $config;

    public static function setUpBeforeClass(): void
    {
        self::$multipart = TestConfig::multipart();
        self::$boundary = self::$multipart['boundary'];
        self::$config = new DownloadConfig();
    }

    public function testItReturnsCorrectContentType(): void
    {
        $resource = FixtureData::create();
        $range = DownloadRange::partial([new DownloadRangeItem(0, 5)], self::$boundary);

        $multipart = new DownloadMultipartResponse($resource, $range);

        self::assertSame(
            'multipart/byteranges; boundary=' . self::$boundary,
            $multipart->getContentType()
        );
    }

    public function testItOutputsCorrectMultipartBodyWithDataResource(): void
    {
        $resource = FixtureData::create();
        $fixture = TestConfig::resourceData();
        $content = $fixture['content'];
        $totalSize = $resource->getFilesize();

        $item1 = new DownloadRangeItem(0, 9);
        $item2 = new DownloadRangeItem(15, 24);

        $range = DownloadRange::partial([$item1, $item2], self::$boundary);
        $multipart = new DownloadMultipartResponse($resource, $range);

        $chunk1 = substr($content, 0, 10);
        $chunk2 = substr($content, 15, 10);

        $expectedBody = "--" . self::$boundary . "\r\n"
            . "Content-Type: {$resource->getMime()}\r\n"
            . "Content-Range: bytes 0-9/$totalSize\r\n\r\n"
            . $chunk1 . "\r\n"
            . "--" . self::$boundary . "\r\n"
            . "Content-Type: {$resource->getMime()}\r\n"
            . "Content-Range: bytes 15-24/$totalSize\r\n\r\n"
            . $chunk2 . "\r\n"
            . "--" . self::$boundary . "--\r\n";

        ob_start();
        $multipart->output(self::$config);
        $output = ob_get_clean();

        self::assertSame($expectedBody, $output);
        self::assertSame(strlen($expectedBody), $multipart->getContentLength());
    }

    public function testItOutputsCorrectMultipartBodyWithFileResource(): void
    {
        $resource = FixtureFile::create('pdf');
        $filepath = (string) $resource->getFilepath();
        $totalSize = $resource->getFilesize();

        $item1 = new DownloadRangeItem(0, 49);
        $item2 = new DownloadRangeItem(100, 149);

        $range = DownloadRange::partial([$item1, $item2], self::$boundary);
        $multipart = new DownloadMultipartResponse($resource, $range);

        $chunk1 = (string) file_get_contents($filepath, false, null, 0, 50);
        $chunk2 = (string) file_get_contents($filepath, false, null, 100, 50);

        $expectedBody = "--" . self::$boundary . "\r\n"
            . "Content-Type: {$resource->getMime()}\r\n"
            . "Content-Range: bytes 0-49/$totalSize\r\n\r\n"
            . $chunk1 . "\r\n"
            . "--" . self::$boundary . "\r\n"
            . "Content-Type: {$resource->getMime()}\r\n"
            . "Content-Range: bytes 100-149/$totalSize\r\n\r\n"
            . $chunk2 . "\r\n"
            . "--" . self::$boundary . "--\r\n";

        ob_start();
        $multipart->output(self::$config);
        $output = ob_get_clean();

        self::assertSame($expectedBody, $output);
        self::assertSame(strlen($expectedBody), $multipart->getContentLength());
    }

    public function testContentLengthStrictlyMatchesOutputSize(): void
    {
        $resource = FixtureData::create();
        $items = [
            new DownloadRangeItem(0, 4),
            new DownloadRangeItem(10, 14),
            new DownloadRangeItem(20, 29),
        ];

        $range = DownloadRange::partial($items, self::$boundary);
        $multipart = new DownloadMultipartResponse($resource, $range);

        ob_start();
        $multipart->output(self::$config);
        $output = ob_get_clean();

        self::assertSame(strlen($output), $multipart->getContentLength());
    }
}
