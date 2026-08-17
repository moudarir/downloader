<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use Moudarir\Downloader\Range\DownloadRangeResult;
use PHPUnit\Framework\TestCase;

final class DownloadRangeResultTest extends TestCase
{

    public function testInvalidCreatesInvalidResult(): void
    {
        $result = DownloadRangeResult::invalid();

        self::assertTrue($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testUnsatisfiableCreatesUnsatisfiableResult(): void
    {
        $result = DownloadRangeResult::unsatisfiable();

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testValidCreatesValidResult(): void
    {
        $range = DownloadRange::partial([new DownloadRangeItem(0, 9)], null);
        $result = DownloadRangeResult::valid($range);

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());
        self::assertSame($range, $result->getRange());
    }

    public function testValidResultReturnsTheOriginalRange(): void
    {
        $range = DownloadRange::partial(
            [
                new DownloadRangeItem(20, 29),
                new DownloadRangeItem(40, 49),
            ],
            'test-boundary'
        );

        $result = DownloadRangeResult::valid($range);

        self::assertSame($range, $result->getRange());
        self::assertSame($range->getItems(), $result->getRange()?->getItems());
    }
}
