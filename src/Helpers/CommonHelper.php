<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Helpers;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Exceptions\DownloadException;

final class CommonHelper
{

    public static function nullIfEmpty(?string $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $data = trim($data);
        return $data === '' ? null : $data;
    }

    /**
     * @throws DownloadException
     */
    public static function validateHeaderName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || !preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            throw DownloadException::invalidHeaderName($name);
        }

        return DownloadConfig::VALID_HEADERS[strtolower($name)]
            ?? throw DownloadException::invalidHeaderName($name);
    }

    /**
     * Validate an HTTP header value.
     *
     * CR and LF characters are forbidden to prevent HTTP header injection.
     *
     * @throws DownloadException
     */
    public static function validateHeaderValue(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw DownloadException::invalidHeaderValue($value);
        }

        return $value;
    }

    public static function removeAccents(string $text): string
    {
        if (!preg_match('/[\x80-\xff]/', $text)) {
            return $text;
        }

        $text = strtr($text, [
            // Decompositions for Latin-1 Supplement.
            'ª' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'Ç' => 'C', 'ç' => 'c',
            'ð' => 'd', 'Ð' => 'D',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ñ' => 'n', 'Ñ' => 'N',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'º' => 'o',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ß' => 'ss', 'ẞ' => 'SS',
            'þ' => 'th', 'Þ' => 'TH',
            'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
            // Decompositions for Latin Extended-A.
            'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
            'Ă' => 'A', 'Ā' => 'A', 'Ą' => 'A',
            'Ć' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Č' => 'C',
            'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c',
            'Ď' => 'D', 'Đ' => 'D',
            'ď' => 'd', 'đ' => 'd',
            'Ē' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
            'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
            'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G',
            'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g',
            'Ĥ' => 'H', 'Ħ' => 'H',
            'ĥ' => 'h', 'ħ' => 'h',
            'Ĩ' => 'I', 'Ī' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ĳ' => 'IJ',
            'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij',
            'Ĵ' => 'J', 'ĵ' => 'j',
            'Ķ' => 'K', 'ķ' => 'k', 'ĸ' => 'k',
            'Ĺ' => 'L', 'Ļ' => 'L', 'Ľ' => 'L', 'Ŀ' => 'L', 'Ł' => 'L',
            'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ŀ' => 'l', 'ł' => 'l',
            'Ń' => 'N', 'Ņ' => 'N', 'Ň' => 'N', 'Ŋ' => 'N',
            'ń' => 'n', 'ņ' => 'n', 'ň' => 'n', 'ŉ' => 'n', 'ŋ' => 'n',
            'Ō' => 'O', 'Ŏ' => 'O', 'Ő' => 'O', 'Œ' => 'OE',
            'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o', 'œ' => 'oe',
            'Ŕ' => 'R', 'Ŗ' => 'R', 'Ř' => 'R',
            'ŕ' => 'r', 'ŗ' => 'r', 'ř' => 'r',
            'Ś' => 'S', 'Ŝ' => 'S', 'Ş' => 'S', 'Š' => 'S',
            'ś' => 's', 'ŝ' => 's', 'ş' => 's', 'š' => 's', 'ſ' => 's',
            'Ţ' => 'T', 'Ť' => 'T', 'Ŧ' => 'T',
            'ţ' => 't', 'ť' => 't', 'ŧ' => 't',
            'Ũ' => 'U', 'Ū' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
            'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
            'Ŵ' => 'W', 'ŵ' => 'w',
            'Ŷ' => 'Y',
            'ŷ' => 'y', 'Ÿ' => 'Y',
            'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z',
            'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
            // Decompositions for Latin Extended-B.
            'Ə' => 'E', 'ǝ' => 'e',
            'Ș' => 'S', 'ș' => 's',
            'Ț' => 'T', 'ț' => 't',
            // Euro sign.
            '€' => 'E',
            // GBP (Pound) sign.
            '£' => '',
            // Vowels with diacritic (Vietnamese). Unmarked.
            'Ơ' => 'O', 'ơ' => 'o',
            'Ư' => 'U', 'ư' => 'u',
            // Grave accent.
            'Ầ' => 'A', 'Ằ' => 'A', 'ầ' => 'a', 'ằ' => 'a',
            'Ề' => 'E', 'ề' => 'e',
            'Ồ' => 'O', 'Ờ' => 'O', 'ồ' => 'o', 'ờ' => 'o',
            'Ừ' => 'U', 'ừ' => 'u',
            'Ỳ' => 'Y', 'ỳ' => 'y',
            // Hook.
            'Ả' => 'A', 'Ẩ' => 'A', 'Ẳ' => 'A',
            'ả' => 'a', 'ẩ' => 'a', 'ẳ' => 'a',
            'Ẻ' => 'E', 'Ể' => 'E', 'ẻ' => 'e', 'ể' => 'e',
            'Ỉ' => 'I', 'ỉ' => 'i',
            'Ỏ' => 'O', 'Ổ' => 'O', 'Ở' => 'O',
            'ỏ' => 'o', 'ổ' => 'o', 'ở' => 'o',
            'Ủ' => 'U', 'Ử' => 'U', 'ủ' => 'u', 'ử' => 'u',
            'Ỷ' => 'Y', 'ỷ' => 'y',
            // Tilde.
            'Ẫ' => 'A', 'Ẵ' => 'A', 'ẫ' => 'a', 'ẵ' => 'a',
            'Ẽ' => 'E', 'Ễ' => 'E', 'ẽ' => 'e', 'ễ' => 'e',
            'Ỗ' => 'O', 'Ỡ' => 'O', 'ỗ' => 'o', 'ỡ' => 'o',
            'Ữ' => 'U', 'ữ' => 'u',
            'Ỹ' => 'Y', 'ỹ' => 'y',
            // Acute accent.
            'Ấ' => 'A', 'Ắ' => 'A', 'ấ' => 'a', 'ắ' => 'a',
            'Ế' => 'E', 'ế' => 'e',
            'Ố' => 'O', 'Ớ' => 'O', 'ố' => 'o', 'ớ' => 'o',
            'Ứ' => 'U', 'ứ' => 'u',
            // Dot below.
            'Ạ' => 'A', 'Ậ' => 'A', 'Ặ' => 'A',
            'ạ' => 'a', 'ậ' => 'a', 'ặ' => 'a',
            'Ẹ' => 'E', 'Ệ' => 'E', 'ẹ' => 'e', 'ệ' => 'e',
            'Ị' => 'I', 'ị' => 'i',
            'Ọ' => 'O', 'Ộ' => 'O', 'Ợ' => 'O',
            'ọ' => 'o', 'ộ' => 'o', 'ợ' => 'o',
            'Ụ' => 'U', 'Ự' => 'U', 'ụ' => 'u', 'ự' => 'u',
            'Ỵ' => 'Y', 'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin).
            'ɑ' => 'a',
            // Macron.
            'Ǖ' => 'U', 'ǖ' => 'u',
            // Acute accent.
            'Ǘ' => 'U', 'ǘ' => 'u',
            // Caron.
            'Ǎ' => 'A', 'ǎ' => 'a',
            'Ǐ' => 'I', 'ǐ' => 'i',
            'Ǒ' => 'O', 'ǒ' => 'o',
            'Ǔ' => 'U', 'Ǚ' => 'U',
            'ǔ' => 'u', 'ǚ' => 'u',
            // Grave accent.
            'Ǜ' => 'U', 'ǜ' => 'u',
        ]);

        /*
         * Handle Unicode strings written with decomposed combining marks,
         * e.g. "e\u{0301}" instead of "é".
         *
         * Do not use the /u modifier on the main sanitization logic.
         * Only remove Unicode combining marks when the input is valid UTF-8.
         */
        if (preg_match('//u', $text) === 1) {
            $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
        }

        return $text;
    }
}
