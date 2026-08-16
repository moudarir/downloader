<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Http\DownloadPreconditionResult;
use Moudarir\Downloader\Http\DownloadPreconditions;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Resources\DownloadResource;
use PHPUnit\Framework\TestCase;

final class DownloadPreconditionsTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    public function testItProceedWhenNoPreconditionIsPresent(): void
    {
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItProceedWhenIfMatchMatches(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = $etag->getValue();

        $result = $this->evaluate($etag);

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItFailWhenIfMatchDoesNotMatch(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = '"etag-inexistant"';

        $result = $this->evaluate($etag);

        self::assertFalse($result->isOk());
        self::assertSame(412, $result->getStatusCode());
    }

    public function testItProceedWhenIfMatchIsWildcard(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = '*';

        $result = $this->evaluate($etag);

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testIfUnmodifiedSinceIsEvaluatedWhenItIsTheOnlyPrecondition(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(1000);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItFailWhenIfUnmodifiedSinceIsOlderThanLastModified(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(999);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertFalse($result->isOk());
        self::assertSame(412, $result->getStatusCode());
    }

    public function testItProceedWhenIfUnmodifiedSinceIsNewerThanLastModified(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(1001);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testIfMatchTakesPrecedenceOverIfUnmodifiedSinceWhenIfMatchFails(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = '"etag-inexistant"';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(1000);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertFalse($result->isOk());
        self::assertSame(412, $result->getStatusCode());
    }

    public function testIfUnmodifiedSinceIsIgnoredWhenIfMatchSucceeds(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = $etag->getValue();
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(999);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfNoneMatchMatches(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag->getValue();

        $result = $this->evaluate($etag);

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    public function testItProceedWhenIfNoneMatchDoesNotMatch(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-inexistant"';

        $result = $this->evaluate($etag);

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfNoneMatchIsWildcard(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = '*';

        $result = $this->evaluate($etag);

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfModifiedSinceMatchesLastModified(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(1000);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    public function testItProceedWhenIfModifiedSinceIsOlderThanLastModified(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(999);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfModifiedSinceIsNewerThanLastModified(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(1001);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSinceWhenIfNoneMatchMatches(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag->getValue();
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(999);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    public function testIfModifiedSinceIsIgnoredWhenIfNoneMatchDoesNotMatch(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-inexistant"';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(1000);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testIfMatchAndIfUnmodifiedSinceAreBothSuccessful(): void
    {
        $etag = $this->etag();

        $_SERVER['HTTP_IF_MATCH'] = $etag->getValue();
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(1000);

        $result = $this->evaluate(
            $etag,
            lastModified: 1000
        );

        self::assertTrue($result->isOk());
        self::assertSame(200, $result->getStatusCode());
    }

    public function testHeadRequestReturnsNotModifiedForMatchingIfNoneMatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $etag = $this->etag();

        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag->getValue();

        $result = $this->evaluate($etag);

        self::assertFalse($result->isOk());
        self::assertSame(304, $result->getStatusCode());
    }

    private function evaluate(
        ?DownloadETag $etag = null,
        int $lastModified = 1000,
    ): DownloadPreconditionResult {
        $resource = $this->resource($lastModified);

        $etag ??= $this->etag();

        $request = DownloadRequest::create();

        return DownloadPreconditions::evaluate(
            $request,
            $resource,
            $etag
        );
    }

    private function etag(): DownloadETag
    {
        return DownloadETag::create(
            $this->resource(1000),
            ETagStrategy::MTIME
        );
    }

    private function resource(int $lastModified): DownloadResource
    {
        return new readonly class($lastModified) implements DownloadResource {
            public function __construct(
                private int $lastModified,
            ) {
            }

            public function getFilename(): string
            {
                return 'test.bin';
            }

            public function getFilesize(): int
            {
                return 100;
            }

            public function getMime(): string
            {
                return 'application/octet-stream';
            }

            public function getLastModified(): ?int
            {
                return $this->lastModified;
            }

            public function output(int $length, int $start = 0): void
            {
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

    private function httpDate(int $timestamp): string
    {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
