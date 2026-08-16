<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DownloadPartialHttpTest extends TestCase
{
    private const string URL = 'http://localhost:8080/download-partial.php';

    /**
     * @return array{
     *     status: int,
     *     headers: array<string, string>,
     *     body: string
     * }
     */
    private function request(
        string $method = 'GET',
        array $headers = [],
        bool $downloadBody = false,
    ): array {
        $curl = curl_init(self::URL);

        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $body = '';

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $header = trim($header);

                if ($header === '' || !str_contains($header, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $header, 2);

                $responseHeaders[strtolower(trim($name))] = trim($value);

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use (&$body, $downloadBody): int {
                if ($downloadBody) {
                    $body .= $data;
                }

                return strlen($data);
            },
            CURLOPT_HTTPHEADER => self::formatHeaders($headers),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_NOBODY => !$downloadBody && in_array($method, ['GET', 'HEAD'], true),
        ]);

        if ($method === 'HEAD') {
            curl_setopt($curl, CURLOPT_NOBODY, true);
        }

        $result = curl_exec($curl);

        if ($result === false) {
            $message = curl_error($curl);
            curl_close($curl);

            throw new RuntimeException(
                'HTTP request failed: ' . $message
            );
        }

        $status = curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private static function formatHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            $result[] = $name . ': ' . $value;
        }

        return $result;
    }

    public function testWithoutRangeReturnsCompleteResponse(): void
    {
        $response = $this->request('HEAD');

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            'bytes',
            $response['headers']['accept-ranges'] ?? null
        );

        self::assertSame(
            'inline; filename="video.mov"; filename*=UTF-8\'\'video.mov',
            $response['headers']['content-disposition'] ?? null
        );

        self::assertSame(
            'video/quicktime',
            $response['headers']['content-type'] ?? null
        );

        self::assertArrayHasKey(
            'content-length',
            $response['headers']
        );
    }

    public function testItReturnsSingleRange(): void
    {
        $response = $this->request(
            'GET',
            [
                'Range' => 'bytes=0-9',
            ],
            true
        );

        self::assertSame(
            206,
            $response['status']
        );

        self::assertSame(
            '10',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            'bytes 0-9/20738368',
            $response['headers']['content-range'] ?? null
        );

        self::assertSame(
            'video/quicktime',
            $response['headers']['content-type'] ?? null
        );

        self::assertSame(
            10,
            strlen($response['body'])
        );
    }

    public function testItReturnsMultipartResponseForMultipleRanges(): void
    {
        $response = $this->request(
            'GET',
            [
                'Range' => 'bytes=0-9,20-29',
            ],
            true
        );

        self::assertSame(
            206,
            $response['status']
        );

        $contentType = $response['headers']['content-type'] ?? null;

        self::assertNotNull($contentType);

        self::assertMatchesRegularExpression(
            '/^multipart\/byteranges; boundary=[a-f0-9]{32}$/',
            $contentType
        );

        self::assertSame(
            '272',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            272,
            strlen($response['body'])
        );

        self::assertStringContainsString(
            "Content-Range: bytes 0-9/20738368\r\n",
            $response['body']
        );

        self::assertStringContainsString(
            "Content-Range: bytes 20-29/20738368\r\n",
            $response['body']
        );
    }

    public function testItReturns416ForUnsatisfiableRange(): void
    {
        $response = $this->request(
            'GET',
            [
                'Range' => 'bytes=999999999-',
            ],
            true
        );

        self::assertSame(
            416,
            $response['status']
        );

        self::assertSame(
            'bytes */20738368',
            $response['headers']['content-range'] ?? null
        );

        self::assertSame(
            '0',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            '',
            $response['body']
        );
    }

    public function testInvalidRangeFallsBackToCompleteResponse(): void
    {
        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=invalid',
            ]
        );

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            '20738368',
            $response['headers']['content-length'] ?? null
        );

        self::assertArrayNotHasKey(
            'content-range',
            $response['headers']
        );
    }

    public function testIfRangeMatchingEtagReturnsPartialResponse(): void
    {
        $head = $this->request('HEAD');

        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => $etag,
            ]
        );

        self::assertSame(
            206,
            $response['status']
        );

        self::assertSame(
            '10',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            'bytes 0-9/20738368',
            $response['headers']['content-range'] ?? null
        );
    }

    public function testIfRangeWithDifferentEtagFallsBackToCompleteResponse(): void
    {
        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => '"etag-inexistant"',
            ]
        );

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            '20738368',
            $response['headers']['content-length'] ?? null
        );

        self::assertArrayNotHasKey(
            'content-range',
            $response['headers']
        );
    }

    public function testWeakIfRangeEtagFallsBackToCompleteResponse(): void
    {
        $head = $this->request('HEAD');

        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => 'W/' . $etag,
            ]
        );

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            '20738368',
            $response['headers']['content-length'] ?? null
        );

        self::assertArrayNotHasKey(
            'content-range',
            $response['headers']
        );
    }

    public function testIfRangeMatchingDateReturnsPartialResponse(): void
    {
        $head = $this->request('HEAD');

        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => $lastModified,
            ]
        );

        self::assertSame(
            206,
            $response['status']
        );

        self::assertSame(
            '10',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            'bytes 0-9/20738368',
            $response['headers']['content-range'] ?? null
        );
    }

    public function testIfRangeWithDifferentDateFallsBackToCompleteResponse(): void
    {
        $head = $this->request('HEAD');

        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $differentDate = gmdate(
                'D, d M Y H:i:s',
                $timestamp - 86400
            ) . ' GMT';

        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => $differentDate,
            ]
        );

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            '20738368',
            $response['headers']['content-length'] ?? null
        );

        self::assertArrayNotHasKey(
            'content-range',
            $response['headers']
        );
    }

    public function testIfRangeWithInvalidDateFallsBackToCompleteResponse(): void
    {
        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9',
                'If-Range' => 'invalid-date',
            ]
        );

        self::assertSame(
            200,
            $response['status']
        );

        self::assertSame(
            '20738368',
            $response['headers']['content-length'] ?? null
        );

        self::assertArrayNotHasKey(
            'content-range',
            $response['headers']
        );
    }

    public function testMultipleRangesThatOverlapAreMerged(): void
    {
        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9,5-19',
            ]
        );

        self::assertSame(
            206,
            $response['status']
        );

        self::assertSame(
            '20',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            'bytes 0-19/20738368',
            $response['headers']['content-range'] ?? null
        );

        self::assertSame(
            'video/quicktime',
            $response['headers']['content-type'] ?? null
        );
    }

    public function testMultipleRangesThatAreAdjacentAreMerged(): void
    {
        $response = $this->request(
            'HEAD',
            [
                'Range' => 'bytes=0-9,10-19',
            ]
        );

        self::assertSame(
            206,
            $response['status']
        );

        self::assertSame(
            '20',
            $response['headers']['content-length'] ?? null
        );

        self::assertSame(
            'bytes 0-19/20738368',
            $response['headers']['content-range'] ?? null
        );
    }
}