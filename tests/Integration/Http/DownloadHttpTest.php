<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Integration\Http;

use Moudarir\Downloader\Enums\StatusCode;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DownloadHttpTest extends TestCase
{

    private const string URL = 'http://localhost:8080/download.php';

    /**
     * @return array{
     *     status: int,
     *     headers: array<string, string>,
     *     body: string
     * }
     */
    private function request(string $method = 'GET', array $headers = [], bool $downloadBody = false): array
    {
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

            throw new RuntimeException('HTTP request failed: ' . $message);
        }

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

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

    public function testGetReturnsOk(): void
    {
        $response = $this->request();

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testGetReturnsExpectedHeaders(): void
    {
        $response = $this->request();

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame(
            'attachment; filename="video.mov"; filename*=UTF-8\'\'video.mov',
            $response['headers']['content-disposition'] ?? null
        );
        self::assertSame('video/quicktime', $response['headers']['content-type'] ?? null);
        self::assertSame('private, must-revalidate', $response['headers']['cache-control'] ?? null);
        self::assertArrayHasKey('etag', $response['headers']);
        self::assertArrayHasKey('last-modified', $response['headers']);
        self::assertArrayHasKey('content-length', $response['headers']);
    }

    public function testHeadReturnsOk(): void
    {
        $response = $this->request('HEAD');

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testHeadReturnsExpectedHeaders(): void
    {
        $response = $this->request('HEAD');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame(
            'attachment; filename="video.mov"; filename*=UTF-8\'\'video.mov',
            $response['headers']['content-disposition'] ?? null
        );
        self::assertSame('video/quicktime', $response['headers']['content-type'] ?? null);
        self::assertSame('private, must-revalidate', $response['headers']['cache-control'] ?? null);
        self::assertArrayHasKey('etag', $response['headers']);
        self::assertArrayHasKey('last-modified', $response['headers']);
        self::assertArrayHasKey('content-length', $response['headers']);
        self::assertSame('', $response['body']);
    }

    public function testPostIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = $this->request('POST');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testPutIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = $this->request('PUT');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testDeleteIsRejectedByDownloadRequestButEndpointCurrentlyReturnsOk(): void
    {
        $response = $this->request('DELETE');

        self::assertSame(StatusCode::OK->value, $response['status']);
        self::assertSame('', $response['body']);
        self::assertSame('text/html; charset=UTF-8', $response['headers']['content-type'] ?? null);
    }

    public function testIfNoneMatchMatchingEtagReturnsNotModified(): void
    {
        $head = $this->request('HEAD');

        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = $this->request('GET', ['If-None-Match' => $etag]);

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testIfNoneMatchWithDifferentEtagReturnsOk(): void
    {
        $response = $this->request('GET', ['If-None-Match' => '"etag-inexistant"']);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfNoneMatchWildcardReturnsNotModified(): void
    {
        $response = $this->request('GET', ['If-None-Match' => '*']);

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testIfModifiedSinceMatchingLastModifiedReturnsNotModified(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = $this->request('GET', ['If-Modified-Since' => $lastModified]);

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testIfModifiedSinceOlderThanLastModifiedReturnsOk(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $olderDate = gmdate('D, d M Y H:i:s', $timestamp - 86400) . ' GMT';
        $response = $this->request('GET', ['If-Modified-Since' => $olderDate]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfModifiedSinceNewerThanLastModifiedReturnsNotModified(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $newerDate = gmdate('D, d M Y H:i:s', $timestamp + 86400) . ' GMT';
        $response = $this->request('GET', ['If-Modified-Since' => $newerDate]);

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        $head = $this->request('HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = $this->request(
            'GET',
            [
                'If-None-Match' => $etag,
                'If-Modified-Since' => 'Thu, 01 Jan 1970 00:00:00 GMT',
            ]
        );

        self::assertSame(StatusCode::NOT_MODIFIED->value, $response['status']);
    }

    public function testIfNoneMatchDifferentEtagIgnoresIfModifiedSince(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = $this->request(
            'GET',
            [
                'If-None-Match' => '"etag-inexistant"',
                'If-Modified-Since' => $lastModified,
            ]
        );

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfMatchMatchingEtagReturnsOk(): void
    {
        $head = $this->request('HEAD');
        $etag = $head['headers']['etag'] ?? null;

        self::assertNotNull($etag);

        $response = $this->request('GET', ['If-Match' => $etag]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfMatchDifferentEtagReturnsPreconditionFailed(): void
    {
        $response = $this->request('GET', ['If-Match' => '"etag-inexistant"']);

        self::assertSame(StatusCode::PRECONDITION_FAILED->value, $response['status']);
        self::assertSame('0', $response['headers']['content-length'] ?? null);
    }

    public function testIfMatchWildcardReturnsOk(): void
    {
        $response = $this->request('GET', ['If-Match' => '*']);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfUnmodifiedSinceMatchingLastModifiedReturnsOk(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $response = $this->request('GET', ['If-Unmodified-Since' => $lastModified]);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }

    public function testIfUnmodifiedSinceOlderThanLastModifiedReturnsPreconditionFailed(): void
    {
        $head = $this->request('HEAD');
        $lastModified = $head['headers']['last-modified'] ?? null;

        self::assertNotNull($lastModified);

        $timestamp = strtotime($lastModified);

        self::assertNotFalse($timestamp);

        $olderDate = gmdate('D, d M Y H:i:s', $timestamp - 86400) . ' GMT';
        $response = $this->request('GET', ['If-Unmodified-Since' => $olderDate]);

        self::assertSame(StatusCode::PRECONDITION_FAILED->value, $response['status']);
    }

    public function testIfUnmodifiedSinceNewerThanLastModifiedReturnsOk(): void
    {
        $response = $this->request('GET', ['If-Unmodified-Since' => 'Thu, 01 Jan 2027 00:00:00 GMT']);

        self::assertSame(StatusCode::OK->value, $response['status']);
    }
}
