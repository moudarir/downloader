<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadFile;
use Moudarir\Downloader\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class DownloadFileTest extends TestCase
{

    private static array $fixture;

    private static string $filepath;

    private static DownloadConfig $config;

    public static function setUpBeforeClass(): void
    {
        self::$fixture = TestConfig::resourceFile('pdf');
        self::$filepath = TestConfig::resourcePath() . self::$fixture['basename'];
        self::$config = new DownloadConfig();
    }

    public function testItThrowsWhenFileDoesNotExist(): void
    {
        $missing = self::$filepath . '-missing';

        self::expectException(DownloadException::class);

        DownloadFile::create($missing, 'missing.txt', 'text/plain');
    }

    public function testItThrowsWhenFilepathIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The specified file path was not found.");

        DownloadFile::create('', 'test.txt', 'text/plain');
    }

    public function testItSupportsExpectedEtagStrategies(): void
    {
        $resource = DownloadFile::create(
            self::$filepath,
            self::$fixture['filename'],
            self::$fixture['mime']
        );

        self::assertSame(
            [
                ETagStrategy::MTIME,
                ETagStrategy::INODE,
                ETagStrategy::MD5,
                ETagStrategy::SHA256,
                ETagStrategy::SHA512,
            ],
            $resource->getSupportedETagStrategies()
        );
    }

    public function testOutputFullFile(): void
    {
        $resource = DownloadFile::create(
            self::$filepath,
            self::$fixture['filename'],
            self::$fixture['mime']
        );

        ob_start();
        $resource->output(self::$config, $resource->getFilesize());
        $output = ob_get_clean();

        $this->assertSame(filesize(self::$filepath), strlen($output));
        $this->assertSame(file_get_contents(self::$filepath), $output);
    }

    public function testOutputPartialContentWithOffset(): void
    {
        $length = 100;
        $start = 10;
        $resource = DownloadFile::create(
            self::$filepath,
            self::$fixture['filename'],
            self::$fixture['mime']
        );

        ob_start();
        $resource->output(self::$config, $length, $start);
        $output = ob_get_clean();

        $expectedContent = file_get_contents(self::$filepath, false, null, $start, $length);

        $this->assertSame($length, strlen($output));
        $this->assertSame($expectedContent, $output);
    }

    public function testOutputWithZeroLengthReturnsNothing(): void
    {
        $resource = DownloadFile::create(
            self::$filepath,
            self::$fixture['filename'],
            self::$fixture['mime']
        );

        ob_start();
        $resource->output(self::$config, 0);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
