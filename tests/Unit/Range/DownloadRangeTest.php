<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\Range\DownloadRange;
use Moudarir\Downloader\Range\DownloadRangeItem;
use PHPUnit\Framework\TestCase;

final class DownloadRangeTest extends TestCase
{

    public function testFullCreatesCompleteRange(): void
    {
        $range = DownloadRange::full(100);

        self::assertFalse($range->isPartial());
        self::assertFalse($range->isMultipart());
        self::assertNull($range->getBoundary());
        self::assertCount(1, $range->getItems());

        $firstItem = $range->getFirstItem();

        self::assertSame(
            [0, 99, 100],
            [$firstItem->getStart(), $firstItem->getEnd(), $firstItem->getLength()]
        );
    }

    public function testPartialCreatesPartialRange(): void
    {
        $items = [new DownloadRangeItem(0, 9)];
        $range = DownloadRange::partial($items, null);

        self::assertTrue($range->isPartial());
        self::assertFalse($range->isMultipart());
        self::assertNull($range->getBoundary());
        self::assertSame(
            [$items, $items[0]],
            [$range->getItems(), $range->getFirstItem()]
        );
    }

    public function testPartialWithMultipleItemsIsMultipart(): void
    {
        $items = [
            new DownloadRangeItem(0, 9),
            new DownloadRangeItem(20, 29),
        ];

        $boundary = 'test-boundary';
        $range = DownloadRange::partial($items, $boundary);

        self::assertTrue($range->isPartial());
        self::assertTrue($range->isMultipart());
        self::assertSame(
            [$boundary, $items, $items[0]],
            [$range->getBoundary(), $range->getItems(), $range->getFirstItem()]
        );
    }

    public function testIsMultipartDependsOnNumberOfItems(): void
    {
        $single = DownloadRange::partial([new DownloadRangeItem(10, 19)], 'unused-boundary');
        $multiple = DownloadRange::partial(
            [
                new DownloadRangeItem(10, 19),
                new DownloadRangeItem(30, 39),
            ],
            'test-boundary'
        );

        self::assertFalse($single->isMultipart());
        self::assertTrue($multiple->isMultipart());
    }

    public function testGetItemsReturnsItemsInOriginalOrder(): void
    {
        $items = [
            new DownloadRangeItem(20, 29),
            new DownloadRangeItem(0, 9),
        ];

        $range = DownloadRange::partial($items, 'test-boundary');

        self::assertSame(
            [$items, 20],
            [$range->getItems(), $range->getFirstItem()->getStart()]
        );
    }

    public function testGetBoundaryReturnsBoundary(): void
    {
        $boundary = '0123456789abcdef';

        $range = DownloadRange::partial(
            [
                new DownloadRangeItem(0, 9),
                new DownloadRangeItem(20, 29),
            ],
            $boundary
        );

        self::assertSame($boundary, $range->getBoundary());
    }
}
