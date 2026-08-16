<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Http\DownloadMultipartResponse;
use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class DownloadMultipartResponseTest extends TestCase
{

    private const string BOUNDARY = 'test-boundary';

    public function testItBuildsTheMultipartContentType(): void
    {
        $resource = $this->resource();
        $range = $this->range();

        $response = new DownloadMultipartResponse(
            $resource,
            $range
        );

        self::assertSame(
            'multipart/byteranges; boundary=test-boundary',
            $response->getContentType()
        );
    }

    public function testItCalculatesContentLengthForMultipleRanges(): void
    {
        $resource = $this->resource();
        $range = $this->range();

        $response = new DownloadMultipartResponse(
            $resource,
            $range
        );

        $expected =
            strlen(
                "--test-boundary\r\n" .
                "Content-Type: text/plain\r\n" .
                "Content-Range: bytes 0-4/26\r\n" .
                "\r\n"
            )
            + 5
            + 2
            + strlen(
                "--test-boundary\r\n" .
                "Content-Type: text/plain\r\n" .
                "Content-Range: bytes 10-14/26\r\n" .
                "\r\n"
            )
            + 5
            + 2
            + strlen("--test-boundary--\r\n");

        self::assertSame(
            $expected,
            $response->getContentLength()
        );
    }

    public function testItOutputsCompleteMultipartBody(): void
    {
        $resource = $this->resource();
        $range = $this->range();

        $response = new DownloadMultipartResponse(
            $resource,
            $range
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $expected =
            "--test-boundary\r\n" .
            "Content-Type: text/plain\r\n" .
            "Content-Range: bytes 0-4/26\r\n" .
            "\r\n" .
            "abcde\r\n" .
            "--test-boundary\r\n" .
            "Content-Type: text/plain\r\n" .
            "Content-Range: bytes 10-14/26\r\n" .
            "\r\n" .
            "klmno\r\n" .
            "--test-boundary--\r\n";

        self::assertSame(
            $expected,
            $output
        );
    }

    public function testContentLengthMatchesActualOutputLength(): void
    {
        $response = new DownloadMultipartResponse(
            $this->resource(),
            $this->range()
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            $response->getContentLength(),
            strlen($output)
        );
    }

    public function testItIncludesResourceMimeTypeInEveryPart(): void
    {
        $response = new DownloadMultipartResponse(
            $this->resource('video/mp4'),
            $this->range()
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            2,
            substr_count(
                $output,
                "Content-Type: video/mp4\r\n"
            )
        );
    }

    public function testItUsesTheResourceFilesizeInEveryContentRange(): void
    {
        $response = new DownloadMultipartResponse(
            $this->resource(),
            $this->range()
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            2,
            substr_count(
                $output,
                'Content-Range: bytes '
            )
        );

        self::assertStringContainsString(
            'Content-Range: bytes 0-4/26',
            $output
        );

        self::assertStringContainsString(
            'Content-Range: bytes 10-14/26',
            $output
        );
    }

    public function testItEndsWithTheClosingBoundary(): void
    {
        $response = new DownloadMultipartResponse(
            $this->resource(),
            $this->range()
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringEndsWith(
            '--test-boundary--' . "\r\n",
            $output
        );
    }

    public function testItOutputsOnlyRequestedRanges(): void
    {
        $response = new DownloadMultipartResponse(
            $this->resource(),
            DownloadRange::partial(
                [
                    new DownloadRangeItem(5, 7),
                    new DownloadRangeItem(20, 22),
                ],
                self::BOUNDARY
            )
        );

        ob_start();

        try {
            $response->output();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $expected =
            "--test-boundary\r\n" .
            "Content-Type: text/plain\r\n" .
            "Content-Range: bytes 5-7/26\r\n" .
            "\r\n" .
            "fgh\r\n" .
            "--test-boundary\r\n" .
            "Content-Type: text/plain\r\n" .
            "Content-Range: bytes 20-22/26\r\n" .
            "\r\n" .
            "uvw\r\n" .
            "--test-boundary--\r\n";

        self::assertSame(
            $expected,
            $output
        );
    }

    private function range(): DownloadRange
    {
        return DownloadRange::partial(
            [
                new DownloadRangeItem(0, 4),
                new DownloadRangeItem(10, 14),
            ],
            self::BOUNDARY
        );
    }

    private function resource(
        string $mime = 'text/plain',
    ): DownloadResource {
        return new readonly class($mime) implements DownloadResource {

            private const string DATA = 'abcdefghijklmnopqrstuvwxyz';

            public function __construct(
                private string $mime,
            ) {
            }

            public function getFilename(): string
            {
                return 'test.txt';
            }

            public function getFilesize(): int
            {
                return strlen(self::DATA);
            }

            public function getMime(): string
            {
                return $this->mime;
            }

            public function getLastModified(): ?int
            {
                return null;
            }

            public function output(int $length, int $start = 0): void
            {
                echo substr(
                    self::DATA,
                    $start,
                    $length
                );
            }

            public function getFilepath(): ?string
            {
                return null;
            }

            public function getHash(string $algorithm): ?string
            {
                return null;
            }

            public function getSupportedETagStrategies(): array
            {
                return [
                    ETagStrategy::MTIME,
                ];
            }
        };
    }
}
