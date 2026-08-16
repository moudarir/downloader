<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Unit\Helpers;

use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Helpers\CommonHelper;
use PHPUnit\Framework\TestCase;

final class CommonHelperTest extends TestCase
{
    public function testItReturnsNullForNullInput(): void
    {
        self::assertNull(
            CommonHelper::nullIfEmpty(null)
        );
    }

    public function testItReturnsNullForEmptyString(): void
    {
        self::assertNull(
            CommonHelper::nullIfEmpty('')
        );
    }

    public function testItReturnsNullForWhitespaceOnlyString(): void
    {
        self::assertNull(
            CommonHelper::nullIfEmpty('   ')
        );
    }

    public function testItTrimsNonEmptyString(): void
    {
        self::assertSame(
            'hello',
            CommonHelper::nullIfEmpty('  hello  ')
        );
    }

    public function testItReturnsAsciiStringUnchanged(): void
    {
        $value = 'Hello World 123';

        self::assertSame(
            $value,
            CommonHelper::removeAccents($value)
        );
    }

    public function testItRemovesLatinAccents(): void
    {
        self::assertSame(
            'aeiou AEIOU n N c C',
            CommonHelper::removeAccents(
                'àéïôü ÀÉÏÔÜ ñ Ñ ç Ç'
            )
        );
    }

    public function testItConvertsLigatures(): void
    {
        self::assertSame(
            'AE ae OE oe IJ ij',
            CommonHelper::removeAccents(
                'Æ æ Œ œ Ĳ ĳ'
            )
        );
    }

    public function testItConvertsSharpS(): void
    {
        self::assertSame(
            'ss SS',
            CommonHelper::removeAccents('ß ẞ')
        );
    }

    public function testItConvertsSpecialSymbolsHandledByTheMapping(): void
    {
        self::assertSame(
            'E',
            CommonHelper::removeAccents('€')
        );

        self::assertSame(
            '',
            CommonHelper::removeAccents('£')
        );
    }

    public function testItConvertsExtendedLatinCharacters(): void
    {
        self::assertSame(
            'AaCcDdEeGgHhIiJjKkLlNnOoRrSsTtUuWwYyZz',
            CommonHelper::removeAccents(
                'ĀăĆĉĎđĒěĞğĤħĨĩĴĵĶķĹłŃňŌŏŔřŚšŢťŨŭŴŵŶŷŹž'
            )
        );
    }

    public function testItConvertsVietnameseCharacters(): void
    {
        self::assertSame(
            'AOUEaoue',
            CommonHelper::removeAccents(
                'ẢƠỦỀãờưế'
            )
        );
    }

    public function testItConvertsPinyinCharacters(): void
    {
        self::assertSame(
            'UuUuAaIiOoUuUu',
            CommonHelper::removeAccents(
                'ǕǖǗǘǍǎǏǐǑǒǓǔǙǚ'
            )
        );
    }

    public function testItRemovesCombiningMarksFromDecomposedCharacters(): void
    {
        $text = "e\u{0301}";

        self::assertSame(
            'e',
            CommonHelper::removeAccents($text)
        );
    }

    public function testItRemovesMultipleCombiningMarks(): void
    {
        $text = "e\u{0301}\u{0323}";

        self::assertSame(
            'e',
            CommonHelper::removeAccents($text)
        );
    }

    public function testItHandlesAStringContainingBothComposedAndDecomposedCharacters(): void
    {
        $text = "é e\u{0301} à a\u{0300}";

        self::assertSame(
            'e e a a',
            CommonHelper::removeAccents($text)
        );
    }

    public function testItPreservesSpacesAndPunctuation(): void
    {
        $text = 'Été 2026 - déjà prêt !';

        self::assertSame(
            'Ete 2026 - deja pret !',
            CommonHelper::removeAccents($text)
        );
    }

    public function testItReturnsEmptyStringForEmptyInput(): void
    {
        self::assertSame(
            '',
            CommonHelper::removeAccents('')
        );
    }

    public function testItPreservesInvalidUtf8WithoutThrowing(): void
    {
        $text = "hello\xFFworld";

        $result = CommonHelper::removeAccents($text);

        self::assertSame(
            $text,
            $result
        );
    }

    public function testItDoesNotUseLocaleDependentTransliteration(): void
    {
        self::assertSame(
            'video ete.mp4',
            CommonHelper::removeAccents(
                'vidéo été.mp4'
            )
        );
    }

    public function testItAcceptsValidHeaderName(): void
    {
        self::assertSame(
            'Content-Type',
            CommonHelper::validateHeaderName('Content-Type')
        );
    }

    public function testItNormalizesHeaderNameCase(): void
    {
        self::assertSame(
            'Content-Type',
            CommonHelper::validateHeaderName('content-type')
        );
    }

    public function testItTrimsHeaderName(): void
    {
        self::assertSame(
            'ETag',
            CommonHelper::validateHeaderName('  ETag  ')
        );
    }

    public function testItRejectsEmptyHeaderName(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header name'
        );

        CommonHelper::validateHeaderName('');
    }

    public function testItRejectsWhitespaceOnlyHeaderName(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header name'
        );

        CommonHelper::validateHeaderName('   ');
    }

    public function testItRejectsHeaderNameContainingWhitespace(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header name'
        );

        CommonHelper::validateHeaderName('Content Type');
    }

    public function testItRejectsUnknownHeaderName(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header name'
        );

        CommonHelper::validateHeaderName('X-Unknown-Header');
    }

    public function testItAcceptsHeaderValueWithoutControlCharacters(): void
    {
        $value = 'text/plain; charset=utf-8';

        self::assertSame(
            $value,
            CommonHelper::validateHeaderValue($value)
        );
    }

    public function testItPreservesEmptyHeaderValue(): void
    {
        self::assertSame(
            '',
            CommonHelper::validateHeaderValue('')
        );
    }

    public function testItRejectsCarriageReturnInHeaderValue(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header value'
        );

        CommonHelper::validateHeaderValue(
            "text/plain\rX-Test: injected"
        );
    }

    public function testItRejectsLineFeedInHeaderValue(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header value'
        );

        CommonHelper::validateHeaderValue(
            "text/plain\nX-Test: injected"
        );
    }

    public function testItRejectsCarriageReturnLineFeedInHeaderValue(): void
    {
        self::expectException(DownloadException::class);
        self::expectExceptionMessageIsOrContains(
            'Invalid HTTP header value'
        );

        CommonHelper::validateHeaderValue(
            "text/plain\r\nX-Test: injected"
        );
    }
}