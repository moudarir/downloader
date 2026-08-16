<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadData;
use PHPUnit\Framework\TestCase;

final class DownloadDataTest extends TestCase
{
    public function testItCreatesResourceWithValidData(): void
    {
        $resource = DownloadData::create(
            'Hello, World!',
            'hello.txt',
            'text/plain'
        );

        self::assertSame('hello.txt', $resource->getFilename());
        self::assertSame('text/plain', $resource->getMime());
        self::assertSame(13, $resource->getFilesize());
    }

    public function testItTrimsFilename(): void
    {
        $resource = DownloadData::create(
            'Hello',
            '  hello.txt  ',
            'text/plain'
        );

        self::assertSame('hello.txt', $resource->getFilename());
    }

    public function testItUsesDefaultMimeWhenMimeIsNull(): void
    {
        $resource = DownloadData::create(
            'Hello',
            'hello.txt'
        );

        self::assertSame(
            DownloadConfig::DEFAULT_MIME,
            $resource->getMime()
        );
    }

    public function testItUsesDefaultMimeWhenMimeIsEmpty(): void
    {
        $resource = DownloadData::create(
            'Hello',
            'hello.txt',
            ''
        );

        self::assertSame(
            DownloadConfig::DEFAULT_MIME,
            $resource->getMime()
        );
    }

    public function testItReturnsNullForLastModified(): void
    {
        $resource = DownloadData::create(
            'Hello',
            'hello.txt'
        );

        self::assertNull($resource->getLastModified());
    }

    public function testItReturnsNullForFilepath(): void
    {
        $resource = DownloadData::create(
            'Hello',
            'hello.txt'
        );

        self::assertNull($resource->getFilepath());
    }

    public function testItThrowsWhenDataIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'The data source cannot be empty.'
        );

        DownloadData::create(
            '',
            'hello.txt'
        );
    }

    public function testItThrowsWhenFilenameIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'A filename is required when downloading data from memory.'
        );

        DownloadData::create(
            'Hello',
            ''
        );
    }

    public function testItThrowsWhenFilenameContainsOnlyWhitespace(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'A filename is required when downloading data from memory.'
        );

        DownloadData::create(
            'Hello',
            '   '
        );
    }

    public function testItReturnsMd5Hash(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create(
            $data,
            'hello.txt'
        );

        self::assertSame(
            md5($data),
            $resource->getHash('md5')
        );
    }

    public function testItReturnsSha256Hash(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create(
            $data,
            'hello.txt'
        );

        self::assertSame(
            hash('sha256', $data),
            $resource->getHash('sha256')
        );
    }

    public function testItReturnsSha512Hash(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create(
            $data,
            'hello.txt'
        );

        self::assertSame(
            hash('sha512', $data),
            $resource->getHash('sha512')
        );
    }

    public function testItReturnsNullForInvalidHashAlgorithm(): void
    {
        $resource = DownloadData::create(
            'Hello',
            'hello.txt'
        );

        self::assertNull(
            $resource->getHash('invalid-algorithm')
        );
    }

    public function testItSupportsExpectedEtagStrategies(): void
    {
        $resource = DownloadData::create('Hello', 'hello.txt');

        self::assertSame(
            [
                ETagStrategy::MD5,
                ETagStrategy::SHA256,
                ETagStrategy::SHA512,
            ],
            $resource->getSupportedETagStrategies()
        );
    }

    public function testItOutputsTheCompleteData(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create($data, 'hello.txt');

        ob_start();

        try {
            $resource->output(strlen($data));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame($data, $output);
    }

    public function testItOutputsOnlyRequestedPartOfData(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create($data, 'hello.txt');

        ob_start();

        try {
            $resource->output(5, 7);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('World', $output);
    }

    public function testItOutputsDataStartingAtZeroByDefault(): void
    {
        $data = 'Hello, World!';

        $resource = DownloadData::create($data, 'hello.txt');

        ob_start();

        try {
            $resource->output(5);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('Hello', $output);
    }

    public function testItOutputsNothingWhenLengthIsZero(): void
    {
        $resource = DownloadData::create('Hello, World!', 'hello.txt');

        ob_start();

        try {
            $resource->output(0);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('', $output);
    }

    public function testItOutputsNothingWhenLengthIsNegative(): void
    {
        $resource = DownloadData::create('Hello, World!', 'hello.txt');

        ob_start();

        try {
            $resource->output(-1);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('', $output);
    }
}
