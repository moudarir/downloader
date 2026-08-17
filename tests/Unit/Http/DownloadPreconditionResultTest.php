<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\StatusCode;
use Moudarir\Downloader\Http\DownloadPreconditionResult;
use PHPUnit\Framework\TestCase;

final class DownloadPreconditionResultTest extends TestCase
{

    public function testProceedCreatesOkResult(): void
    {
        $result = DownloadPreconditionResult::proceed();

        self::assertTrue($result->isOk());
        self::assertSame(StatusCode::OK, $result->getStatusCode());
    }

    public function testNotModifiedCreatesNotModifiedResult(): void
    {
        $result = DownloadPreconditionResult::notModified();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::NOT_MODIFIED, $result->getStatusCode());
    }

    public function testPreconditionFailedCreatesFailedResult(): void
    {
        $result = DownloadPreconditionResult::preconditionFailed();

        self::assertFalse($result->isOk());
        self::assertSame(StatusCode::PRECONDITION_FAILED, $result->getStatusCode());
    }
}
