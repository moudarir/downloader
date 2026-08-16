<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Range;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Range\DownloadRangeParser;
use PHPUnit\Framework\TestCase;

final class DownloadRangeParserTest extends TestCase
{
    private const int FILESIZE = 100;

    public function testItParsesAnExplicitRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isPartial());
        self::assertFalse($range->isMultipart());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(9, $item->getEnd());
        self::assertSame(10, $item->getLength());

        self::assertNull($range->getBoundary());
    }

    public function testItParsesAnOpenEndedRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=90-',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $item = $result->getRange()?->getFirstItem();

        self::assertNotNull($item);
        self::assertSame(90, $item->getStart());
        self::assertSame(99, $item->getEnd());
        self::assertSame(10, $item->getLength());
    }

    public function testItParsesASuffixRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=-10',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $item = $result->getRange()?->getFirstItem();

        self::assertNotNull($item);
        self::assertSame(90, $item->getStart());
        self::assertSame(99, $item->getEnd());
        self::assertSame(10, $item->getLength());
    }

    public function testItLimitsSuffixRangeToFileSize(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=-999999',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $item = $result->getRange()?->getFirstItem();

        self::assertNotNull($item);
        self::assertSame(0, $item->getStart());
        self::assertSame(99, $item->getEnd());
        self::assertSame(100, $item->getLength());
    }

    public function testItClampsEndToFileSize(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=90-999',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $item = $result->getRange()?->getFirstItem();

        self::assertNotNull($item);
        self::assertSame(90, $item->getStart());
        self::assertSame(99, $item->getEnd());
    }

    public function testItRejectsMissingBytesUnit(): void
    {
        $result = DownloadRangeParser::parse(
            'items=0-9',
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItRejectsMalformedRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=abc-def',
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItRejectsEmptyRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=-',
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItRejectsRangeWithEndBeforeStart(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=60-50',
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItRejectsZeroLengthSuffixRange(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=-0',
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItReturnsUnsatisfiableForStartAtFileSize(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=100-',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testItReturnsUnsatisfiableForStartBeyondFileSize(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=999-',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testItIgnoresAnUnsatisfiableRangeWhenAnotherRangeIsValid(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9,999-',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertCount(1, $range->getItems());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(9, $item->getEnd());
    }

    public function testItReturnsUnsatisfiableWhenAllRangesAreUnsatisfiable(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=100-,200-',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testItMergesAdjacentRanges(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9,10-19',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertCount(1, $range->getItems());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(19, $item->getEnd());
    }

    public function testItMergesOverlappingRanges(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9,5-19',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertCount(1, $range->getItems());

        $item = $range->getFirstItem();

        self::assertSame(0, $item->getStart());
        self::assertSame(19, $item->getEnd());
    }

    public function testItSortsRangesBeforeMerging(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=20-29,0-9',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertCount(2, $range->getItems());

        self::assertSame(
            0,
            $range->getItems()[0]->getStart()
        );

        self::assertSame(
            20,
            $range->getItems()[1]->getStart()
        );
    }

    public function testItCreatesMultipartRangeForMultipleDistinctRanges(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9,20-29',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertTrue($range->isMultipart());
        self::assertCount(2, $range->getItems());

        self::assertNotNull($range->getBoundary());

        $boundary = $range->getBoundary();

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $boundary
        );
    }

    public function testItDoesNotCreateMultipartRangeAfterRangesAreMerged(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9,5-19',
            self::FILESIZE
        );

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertFalse($range->isMultipart());
        self::assertNull($range->getBoundary());
    }

    public function testItAcceptsExactlyTheConfiguredMaximumNumberOfRanges(): void
    {
        $ranges = [];

        for ($i = 0; $i < DownloadConfig::MAX_RANGE_ITEMS; ++$i) {
            $start = $i * 2;

            $ranges[] = sprintf(
                '%d-%d',
                $start,
                $start
            );
        }

        $result = DownloadRangeParser::parse(
            'bytes=' . implode(',', $ranges),
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        self::assertNotNull($result->getRange());
    }

    public function testItRejectsMoreThanTheConfiguredMaximumNumberOfRanges(): void
    {
        $ranges = [];

        for ($i = 0; $i <= DownloadConfig::MAX_RANGE_ITEMS; ++$i) {
            $start = $i * 2;

            $ranges[] = sprintf(
                '%d-%d',
                $start,
                $start
            );
        }

        $result = DownloadRangeParser::parse(
            'bytes=' . implode(',', $ranges),
            self::FILESIZE
        );

        self::assertTrue($result->isInvalid());
        self::assertNull($result->getRange());
    }

    public function testItHandlesWhitespaceAroundRanges(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes= 0-9, 20-29 ',
            self::FILESIZE
        );

        self::assertFalse($result->isInvalid());
        self::assertFalse($result->isUnsatisfiable());

        $range = $result->getRange();

        self::assertNotNull($range);
        self::assertCount(2, $range->getItems());

        self::assertSame(
            0,
            $range->getItems()[0]->getStart()
        );

        self::assertSame(
            20,
            $range->getItems()[1]->getStart()
        );
    }

    public function testItReturnsUnsatisfiableForSuffixRangeOnEmptyFile(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=-10',
            0
        );

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }

    public function testItReturnsUnsatisfiableForExplicitRangeOnEmptyFile(): void
    {
        $result = DownloadRangeParser::parse(
            'bytes=0-9',
            0
        );

        self::assertFalse($result->isInvalid());
        self::assertTrue($result->isUnsatisfiable());
        self::assertNull($result->getRange());
    }
}
