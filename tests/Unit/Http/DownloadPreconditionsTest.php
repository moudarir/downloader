<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Http\DownloadPreconditionResult;
use Moudarir\Downloader\Http\DownloadPreconditions;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Tests\Support\FixtureFile;
use PHPUnit\Framework\TestCase;

final class DownloadPreconditionsTest extends TestCase
{

    /**
     * @var array<string, mixed>
     */
    private array $server;

    private static FixtureFile $resource;

    private static DownloadETag $etag;

    private static int $lastModified;

    public static function setUpBeforeClass(): void
    {
        self::$resource = FixtureFile::create('txt');
        self::$etag = DownloadETag::create(self::$resource, ETagStrategy::MTIME);
        self::$lastModified = self::$resource->getLastModified();
    }

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
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItProceedWhenIfMatchMatches(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = self::$etag->getValue();
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItFailWhenIfMatchDoesNotMatch(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = '"etag-inexistant"';
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::PRECONDITION_FAILED, $result->getStatusCode());
    }

    public function testItProceedWhenIfMatchIsWildcard(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = '*';
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testIfUnmodifiedSinceIsEvaluatedWhenItIsTheOnlyPrecondition(): void
    {
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItFailWhenIfUnmodifiedSinceIsOlderThanLastModified(): void
    {
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified - 1);
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::PRECONDITION_FAILED, $result->getStatusCode());
    }

    public function testItProceedWhenIfUnmodifiedSinceIsNewerThanLastModified(): void
    {
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified + 1);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testIfMatchTakesPrecedenceOverIfUnmodifiedSinceWhenIfMatchFails(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = '"etag-inexistant"';
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified);
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::PRECONDITION_FAILED, $result->getStatusCode());
    }

    public function testIfUnmodifiedSinceIsIgnoredWhenIfMatchSucceeds(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = self::$etag->getValue();
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified - 1);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfNoneMatchMatches(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = self::$etag->getValue();
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testItProceedWhenIfNoneMatchDoesNotMatch(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-inexistant"';
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfNoneMatchIsWildcard(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '*';
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfModifiedSinceMatchesLastModified(): void
    {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(self::$lastModified);
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testItProceedWhenIfModifiedSinceIsOlderThanLastModified(): void
    {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(self::$lastModified - 1);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testItReturnNotModifiedWhenIfModifiedSinceIsNewerThanLastModified(): void
    {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(self::$lastModified + 1);
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSinceWhenIfNoneMatchMatches(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = self::$etag->getValue();
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(self::$lastModified - 1);
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testIfModifiedSinceIsIgnoredWhenIfNoneMatchDoesNotMatch(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-inexistant"';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->httpDate(self::$lastModified);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testIfMatchAndIfUnmodifiedSinceAreBothSuccessful(): void
    {
        $_SERVER['HTTP_IF_MATCH'] = self::$etag->getValue();
        $_SERVER['HTTP_IF_UNMODIFIED_SINCE'] = $this->httpDate(self::$lastModified);
        $result = $this->evaluate();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testHeadRequestReturnsNotModifiedForMatchingIfNoneMatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['HTTP_IF_NONE_MATCH'] = self::$etag->getValue();
        $result = $this->evaluate();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    private function evaluate(): DownloadPreconditionResult
    {
        return DownloadPreconditions::evaluate(
            DownloadRequest::create(),
            self::$resource,
            self::$etag
        );
    }

    private function httpDate(int $timestamp): string
    {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
