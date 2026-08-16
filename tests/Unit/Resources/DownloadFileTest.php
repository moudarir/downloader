<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Resources;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DownloadFileTest extends TestCase
{
    private string $filepath;

    protected function setUp(): void
    {
        parent::setUp();

        $filepath = tempnam(
            sys_get_temp_dir(),
            'downloader-test-'
        );

        if ($filepath === false) {
            throw new RuntimeException(
                'Unable to create temporary file.'
            );
        }

        $this->filepath = $filepath;
    }

    protected function tearDown(): void
    {
        if (is_file($this->filepath)) {
            unlink($this->filepath);
        }

        parent::tearDown();
    }

    public function testItCreatesResourceFromFile(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertSame(
            $this->filepath,
            $resource->getFilepath()
        );

        self::assertSame(
            'hello.txt',
            $resource->getFilename()
        );

        self::assertSame(
            strlen($content),
            $resource->getFilesize()
        );

        self::assertSame(
            'text/plain',
            $resource->getMime()
        );

        self::assertNotNull(
            $resource->getLastModified()
        );
    }

    public function testItUsesBasenameAsFilenameWhenFilenameIsNotProvided(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            null,
            'text/plain'
        );

        self::assertSame(
            basename($this->filepath),
            $resource->getFilename()
        );
    }

    public function testItUsesBasenameAsFilenameWhenFilenameIsEmpty(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            '   ',
            'text/plain'
        );

        self::assertSame(
            basename($this->filepath),
            $resource->getFilename()
        );
    }

    public function testItUsesExplicitMimeType(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/custom'
        );

        self::assertSame(
            'text/custom',
            $resource->getMime()
        );
    }

    public function testItUsesDefaultMimeTypeWhenMimeIsEmpty(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt'
        );

        self::assertSame(
            DownloadConfig::DEFAULT_MIME,
            $resource->getMime()
        );
    }

    public function testItTrimsExplicitMimeType(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            '  text/plain  '
        );

        self::assertSame(
            'text/plain',
            $resource->getMime()
        );
    }

    public function testItDetectsMimeTypeAutomatically(): void
    {
        file_put_contents(
            $this->filepath,
            "<?php echo 'Hello';"
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'test.php',
            true
        );

        self::assertSame(
            'application/x-httpd-php',
            $resource->getMime()
        );
    }

    public function testItReturnsLastModifiedTimestamp(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $expected = filemtime($this->filepath);

        self::assertNotFalse($expected);

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertSame(
            $expected,
            $resource->getLastModified()
        );
    }

    public function testItThrowsWhenFileDoesNotExist(): void
    {
        $missing = $this->filepath . '-missing';

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'The specified file path was not found.'
        );

        DownloadFile::create(
            $missing,
            'missing.txt',
            'text/plain'
        );
    }

    public function testItThrowsWhenFilepathIsEmpty(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'The specified file path was not found.'
        );

        DownloadFile::create(
            '',
            'test.txt',
            'text/plain'
        );
    }

    public function testItReturnsMd5Hash(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertSame(
            md5($content),
            $resource->getHash('md5')
        );
    }

    public function testItReturnsSha256Hash(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertSame(
            hash('sha256', $content),
            $resource->getHash('sha256')
        );
    }

    public function testItReturnsSha512Hash(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertSame(
            hash('sha512', $content),
            $resource->getHash('sha512')
        );
    }

    public function testItReturnsNullForInvalidHashAlgorithm(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        self::assertNull(
            $resource->getHash('invalid-algorithm')
        );
    }

    public function testItSupportsExpectedEtagStrategies(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
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

    public function testItOutputsCompleteFileContent(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        ob_start();

        try {
            $resource->output(strlen($content));
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            $content,
            $output
        );
    }

    public function testItOutputsRequestedPartOfFile(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        ob_start();

        try {
            $resource->output(5, 7);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            'World',
            $output
        );
    }

    public function testItStartsAtZeroByDefault(): void
    {
        $content = 'Hello, World!';

        file_put_contents(
            $this->filepath,
            $content
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        ob_start();

        try {
            $resource->output(5);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            'Hello',
            $output
        );
    }

    public function testItOutputsNothingWhenLengthIsZero(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello, World!'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        ob_start();

        try {
            $resource->output(0);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            '',
            $output
        );
    }

    public function testItOutputsNothingWhenLengthIsNegative(): void
    {
        file_put_contents(
            $this->filepath,
            'Hello, World!'
        );

        $resource = DownloadFile::create(
            $this->filepath,
            'hello.txt',
            'text/plain'
        );

        ob_start();

        try {
            $resource->output(-1);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            '',
            $output
        );
    }
}