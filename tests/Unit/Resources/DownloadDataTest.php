<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Resources;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadData;
use Moudarir\Downloader\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class DownloadDataTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private static array $fixture;

    private static DownloadData $resource;

    public static function setUpBeforeClass(): void
    {
        self::$fixture = TestConfig::resourceData();
        self::$resource = DownloadData::create(
            self::$fixture['content'],
            self::$fixture['filename'],
            self::$fixture['mime']
        );
    }

    public function testItThrowsWhenDataIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The data source cannot be empty.");

        DownloadData::create('', self::$fixture['filename'], self::$fixture['mime']);
    }

    public function testItThrowsWhenFilenameIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("A filename is required when downloading data from memory.");

        DownloadData::create('lorem ipsum', '', self::$fixture['mime']);
    }

    public function testItReturnsCorrectGetters(): void
    {
        self::assertSame(self::$fixture['filename'], self::$resource->getFilename());
        self::assertSame(self::$fixture['mime'], self::$resource->getMime());
        self::assertSame(strlen(self::$fixture['content']), self::$resource->getFilesize());
        self::assertNull(self::$resource->getLastModified());
        self::assertNull(self::$resource->getFilepath());
    }

    public function testItSupportsExpectedEtagStrategies(): void
    {
        self::assertSame(
            [
                ETagStrategy::MD5,
                ETagStrategy::SHA256,
                ETagStrategy::SHA512,
            ],
            self::$resource->getSupportedETagStrategies()
        );
    }

    public function testItCalculatesHashCorrectly(): void
    {
        $expectedHash = hash('md5', self::$fixture['content']);

        self::assertSame($expectedHash, self::$resource->getHash('md5'));
        self::assertNull(self::$resource->getHash('invalid_algorithm'));
    }

    public function testOutputFullData(): void
    {
        ob_start();
        self::$resource->output(self::$resource->getFilesize());
        $output = ob_get_clean();

        self::assertSame(strlen(self::$fixture['content']), strlen($output));
        self::assertSame(self::$fixture['content'], $output);
    }

    public function testOutputPartialContentWithOffset(): void
    {
        $length = 20;
        $start = 5;

        ob_start();
        self::$resource->output($length, $start);
        $output = ob_get_clean();

        $expectedContent = substr(self::$fixture['content'], $start, $length);

        self::assertSame($length, strlen($output));
        self::assertSame($expectedContent, $output);
    }

    public function testOutputWithZeroLengthReturnsNothing(): void
    {
        ob_start();
        self::$resource->output(0);
        $output = ob_get_clean();

        self::assertSame('', $output);
    }
}
