<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit;

use Moudarir\Downloader\Download;
use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\Exceptions\DownloadException;
use PHPUnit\Framework\TestCase;

final class DownloadTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private array $server;

    private string $filepath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $_SERVER = [];

        $filepath = tempnam(sys_get_temp_dir(), 'downloader-test-');

        if ($filepath === false) {
            self::fail('Unable to create temporary test file.');
        }

        $this->filepath = $filepath;

        file_put_contents($this->filepath, 'Hello, World!');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        if (is_file($this->filepath)) {
            unlink($this->filepath);
        }

        parent::tearDown();
    }

    public function testFromDataCreatesDefaultResponse(): void
    {
        $response = Download::fromData('Hello, World!', 'hello.txt', 'text/plain');

        $metadata = $response->metadata();

        self::assertSame(ResponseAction::DEFAULT, $metadata->responseAction());
        self::assertSame(StatusCode::OK, $metadata->statusCode());
        self::assertSame(13, $metadata->contentLength());
        self::assertSame('text/plain', $metadata->contentType());
        self::assertSame('hello.txt', $metadata->filename());
    }

    public function testFromDataCreatesPartialResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4';

        $response = Download::fromData(
            'Hello, World!',
            'hello.txt',
            'text/plain',
            ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(ResponseAction::PARTIAL, $metadata->responseAction());
        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertSame(5, $metadata->contentLength());
        self::assertTrue($metadata->hasRange());
        self::assertTrue($metadata->rangeIsPartial());
    }

    public function testFromFileCreatesDefaultResponse(): void
    {
        $response = Download::fromFile($this->filepath, 'hello.txt', 'text/plain');

        $metadata = $response->metadata();

        self::assertSame(ResponseAction::DEFAULT, $metadata->responseAction());
        self::assertSame(StatusCode::OK, $metadata->statusCode());
        self::assertSame(filesize($this->filepath), $metadata->filesize());
        self::assertSame('hello.txt', $metadata->filename());
        self::assertSame('text/plain', $metadata->mimeType());
        self::assertSame($this->filepath, $metadata->filepath());
    }

    public function testFromFileCreatesPartialResponse(): void
    {
        $_SERVER['HTTP_RANGE'] = 'bytes=0-4';

        $response = Download::fromFile(
            $this->filepath,
            'hello.txt',
            'text/plain',
            ResponseAction::PARTIAL
        );

        $metadata = $response->metadata();

        self::assertSame(ResponseAction::PARTIAL, $metadata->responseAction());
        self::assertSame(StatusCode::PARTIAL_CONTENT, $metadata->statusCode());
        self::assertSame(5, $metadata->contentLength());
        self::assertTrue($metadata->rangeIsPartial());
    }

    public function testFromFileSupportsAutomaticMimeDetection(): void
    {
        $phpFile = tempnam(sys_get_temp_dir(), 'downloader-php-');

        self::assertNotFalse($phpFile);

        file_put_contents($phpFile, "<?php echo 'Hello';");

        try {
            $response = Download::fromFile($phpFile, 'test.php', true);

            self::assertSame('application/x-httpd-php', $response->metadata()->mimeType());
        } finally {
            unlink($phpFile);
        }
    }

    public function testFromDataRejectsXSendFile(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The `x-send-file` operation is not supported for in-memory resources.");

        Download::fromData(
            'Hello, World!',
            'hello.txt',
            'text/plain',
            ResponseAction::X_SEND_FILE
        );
    }

    public function testFromDataRejectsXAccelRedirectBecauseInternalUriIsRequired(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("An internal URI is required for X-Accel-Redirect.");

        Download::fromData(
            'Hello, World!',
            'hello.txt',
            'text/plain',
            ResponseAction::X_ACCEL_REDIRECT
        );
    }

    public function testFromFileRejectsMissingInternalUriForXAccelRedirect(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("An internal URI is required for X-Accel-Redirect.");

        Download::fromFile(
            $this->filepath,
            'hello.txt',
            'text/plain',
            ResponseAction::X_ACCEL_REDIRECT
        );
    }

    public function testFromFileAcceptsXAccelRedirectWithInternalUri(): void
    {
        $response = Download::fromFile(
            $this->filepath,
            'hello.txt',
            'text/plain',
            ResponseAction::X_ACCEL_REDIRECT,
            '/protected/download'
        );

        self::assertSame(ResponseAction::X_ACCEL_REDIRECT, $response->metadata()->responseAction());
    }

    public function testFromFileAcceptsXSendFile(): void
    {
        $response = Download::fromFile(
            $this->filepath,
            'hello.txt',
            'text/plain',
            ResponseAction::X_SEND_FILE
        );

        self::assertSame(ResponseAction::X_SEND_FILE, $response->metadata()->responseAction());
        self::assertSame(filesize($this->filepath), $response->metadata()->contentLength());
    }

    public function testFromFileSupportsExplicitETagStrategy(): void
    {
        $response = Download::fromFile(
            $this->filepath,
            'hello.txt',
            'text/plain',
            ResponseAction::DEFAULT,
            null,
            ETagStrategy::SHA256
        );

        self::assertSame(64, strlen($response->metadata()->etagOpaqueValue()));
    }

    public function testFromDataSupportsExplicitETagStrategy(): void
    {
        $response = Download::fromData(
            'Hello, World!',
            'hello.txt',
            'text/plain',
            ResponseAction::DEFAULT,
            ETagStrategy::SHA512
        );

        self::assertSame(128, strlen($response->metadata()->etagOpaqueValue()));
    }

    public function testItReturnsPreconditionResponseWhenIfNoneMatchMatches(): void
    {
        $response = Download::fromData('Hello, World!', 'hello.txt', 'text/plain');
        $etag = $response->metadata()->etagOpaqueValue();

        $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . $etag . '"';

        $response = Download::fromData('Hello, World!', 'hello.txt', 'text/plain');
        $metadata = $response->metadata();

        self::assertSame(StatusCode::NOT_MODIFIED, $metadata->statusCode());
        self::assertSame(0, $metadata->contentLength());
    }

    public function testItReturnsPreconditionFailedWhenIfMatchDoesNotMatch(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = '"etag-inexistant"';

        $response = Download::fromData(
            'Hello, World!',
            'hello.txt',
            'text/plain'
        );

        $metadata = $response->metadata();

        self::assertSame(StatusCode::PRECONDITION_FAILED, $metadata->statusCode());
        self::assertSame(0, $metadata->contentLength());
    }

    public function testItUsesDefaultGetRequestWhenRequestMethodIsAbsent(): void
    {
        $response = Download::fromData(
            'Hello',
            'hello.txt',
            'text/plain'
        );

        self::assertSame(StatusCode::OK, $response->metadata()->statusCode());
    }

    public function testItRejectsUnsupportedRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The HTTP request method `POST` is not supported.");

        Download::fromData('Hello, World!', 'hello.txt', 'text/plain');
    }

    public function testFromDataRejectsEmptyData(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("The data source cannot be empty.");

        Download::fromData('', 'hello.txt', 'text/plain');
    }

    public function testFromDataRejectsEmptyFilename(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains("A filename is required when downloading data from memory.");

        Download::fromData('Hello, World!', '', 'text/plain');
    }
}
