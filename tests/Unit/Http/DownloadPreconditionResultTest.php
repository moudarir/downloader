<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Http;

use Moudarir\Downloader\Enums\PreconditionStatus;
use Moudarir\Downloader\Http\DownloadPreconditionResult;
use PHPUnit\Framework\TestCase;

final class DownloadPreconditionResultTest extends TestCase
{

    public function test_proceed_creates_ok_result(): void
    {
        $result = DownloadPreconditionResult::proceed();

        self::assertTrue($result->isOk());
        self::assertSame(PreconditionStatus::OK, $result->getStatus());
        self::assertSame(200, $result->getStatusCode());
    }

    public function test_not_modified_creates_not_modified_result(): void
    {
        $result = DownloadPreconditionResult::notModified();

        self::assertFalse($result->isOk());
        self::assertSame(PreconditionStatus::NOT_MODIFIED, $result->getStatus());
        self::assertSame(304, $result->getStatusCode());
    }

    public function test_precondition_failed_creates_failed_result(): void
    {
        $result = DownloadPreconditionResult::preconditionFailed();

        self::assertFalse($result->isOk());
        self::assertSame(PreconditionStatus::PRECONDITION_FAILED, $result->getStatus());
        self::assertSame(412, $result->getStatusCode());
    }
}
