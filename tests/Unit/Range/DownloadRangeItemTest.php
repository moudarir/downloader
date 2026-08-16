<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\Range\DownloadRangeItem;
use PHPUnit\Framework\TestCase;

final class DownloadRangeItemTest extends TestCase
{

    public function test_it_returns_range_start(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(10, $item->getStart());
    }

    public function test_it_returns_range_end(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(19, $item->getEnd());
    }

    public function test_it_returns_range_length(): void
    {
        $item = new DownloadRangeItem(10, 19);

        self::assertSame(10, $item->getLength());
    }

    public function test_it_returns_one_for_a_single_byte_range(): void
    {
        $item = new DownloadRangeItem(10, 10);

        self::assertSame(1, $item->getLength());
    }

    public function test_it_returns_correct_length_for_a_range_starting_at_zero(): void
    {
        $item = new DownloadRangeItem(0, 9);

        self::assertSame(10, $item->getLength());
    }
}
