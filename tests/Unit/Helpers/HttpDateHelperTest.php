<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use Moudarir\Downloader\Helpers\HttpDateHelper;
use PHPUnit\Framework\TestCase;

final class HttpDateHelperTest extends TestCase
{
    public function testItParsesImfFixdate(): void
    {
        $date = 'Sun, 09 Aug 2026 11:17:59 GMT';

        self::assertSame(
            strtotime($date),
            HttpDateHelper::toTimestamp($date)
        );
    }

    public function testItParsesRfc850Date(): void
    {
        $date = 'Sunday, 09-Aug-26 11:17:59 GMT';

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            HttpDateHelper::toTimestamp($date)
        );
    }

    public function testItParsesAsctimeDateWithSingleDigitDay(): void
    {
        $date = 'Sun Aug  9 11:17:59 2026';

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            HttpDateHelper::toTimestamp($date)
        );
    }

    public function testItParsesAsctimeDateWithDoubleDigitDay(): void
    {
        $date = 'Wed Jan 14 23:02:18 2026';

        self::assertSame(
            strtotime('Wed, 14 Jan 2026 23:02:18 GMT'),
            HttpDateHelper::toTimestamp($date)
        );
    }

    public function testItReturnsNullForEmptyDate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp('')
        );
    }

    public function testItReturnsNullForWhitespaceOnlyDate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp('   ')
        );
    }

    public function testItReturnsNullForInvalidDate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp('invalid-date')
        );
    }

    public function testItRejectsInvalidImfFixdateDay(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun, 32 Aug 2026 11:17:59 GMT'
            )
        );
    }

    public function testItRejectsInvalidImfFixdateMonth(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun, 09 Foo 2026 11:17:59 GMT'
            )
        );
    }

    public function testItRejectsMissingGmtInImfFixdate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun, 09 Aug 2026 11:17:59'
            )
        );
    }

    public function testItRejectsNonCanonicalImfFixdate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sunday, 09 Aug 2026 11:17:59 GMT'
            )
        );
    }

    public function testItRejectsInvalidRfc850DayOfWeek(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Thursday, 14-Jan-26 23:02:18 GMT'
            )
        );
    }

    public function testItRejectsInvalidRfc850Date(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Wednesday, 31-Feb-26 23:02:18 GMT'
            )
        );
    }

    public function testItRejectsInvalidRfc850Time(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Wednesday, 14-Jan-26 25:02:18 GMT'
            )
        );
    }

    public function testItRejectsInvalidAsctimeDayOfWeek(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun Jan 14 23:02:18 2026'
            )
        );
    }

    public function testItRejectsInvalidAsctimeMonth(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Wed Foo 14 23:02:18 2026'
            )
        );
    }

    public function testItRejectsInvalidAsctimeDate(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Wed Feb 31 23:02:18 2026'
            )
        );
    }

    public function testItRejectsNonCanonicalAsctimeSingleDigitDay(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun Aug 9 11:17:59 2026'
            )
        );
    }

    public function testItRejectsNonCanonicalAsctimeExtraWhitespace(): void
    {
        self::assertNull(
            HttpDateHelper::toTimestamp(
                'Sun  Aug  9 11:17:59 2026'
            )
        );
    }

    public function testItTrimsSurroundingWhitespace(): void
    {
        $date = '  Sun, 09 Aug 2026 11:17:59 GMT  ';

        self::assertSame(
            strtotime('Sun, 09 Aug 2026 11:17:59 GMT'),
            HttpDateHelper::toTimestamp($date)
        );
    }

    public function testItAppliesRfc850FiftyYearRule(): void
    {
        $timezone = new DateTimeZone('GMT');
        $now = new DateTimeImmutable('now', $timezone);

        $expected = $now
            ->modify('+51 years')
            ->modify('-100 years')
            ->setTime(12, 0, 0);

        $date = $expected->format('l, d-M-y H:i:s') . ' GMT';

        $actual = HttpDateHelper::toTimestamp($date);

        self::assertNotNull($actual);

        self::assertSame(
            $expected->format('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', $actual)
        );
    }

    public function testItDoesNotApplyRfc850FiftyYearRuleWithinTheWindow(): void
    {
        $timezone = new DateTimeZone('GMT');
        $now = new DateTimeImmutable('now', $timezone);

        $future = $now
            ->modify('+49 years')
            ->setTime(12, 0, 0);

        $date = $future->format('l, d-M-y H:i:s') . ' GMT';

        $actual = HttpDateHelper::toTimestamp($date);

        self::assertNotNull($actual);

        self::assertSame(
            $future->format('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', $actual)
        );
    }

    public function testItKeepsTheCurrentCenturyForARecentRfc850Date(): void
    {
        $timezone = new DateTimeZone('GMT');
        $now = new DateTimeImmutable('now', $timezone);

        $date = $now->modify('-1 day');

        $rfc850 = $date->format('l, d-M-y H:i:s') . ' GMT';

        $actual = HttpDateHelper::toTimestamp($rfc850);

        self::assertNotNull($actual);

        self::assertSame(
            $date->format('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', $actual)
        );
    }
}
