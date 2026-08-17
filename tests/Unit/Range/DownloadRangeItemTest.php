<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\Range\DownloadRangeItem;
use PHPUnit\Framework\TestCase;

final class DownloadRangeItemTest extends TestCase
{

    public function testItReturnsRangeStart(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(10, $item->getStart());
    }

    public function testItReturnsRangeEnd(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(19, $item->getEnd());
    }

    public function testItReturnsRangeLength(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(10, $item->getLength());
    }

    public function testItReturnsOneForSingleByteRange(): void
    {
        $item = new DownloadRangeItem(10, 10);

        self::assertSame(1, $item->getLength());
    }

    public function testItReturnsCorrectLengthForRangeStartingAtZero(): void
    {
        $item = new DownloadRangeItem(0, 9);

        self::assertSame(10, $item->getLength());
    }
}
