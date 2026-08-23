<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Tests\Support;

use RuntimeException;

final class TestHttp
{

    /**
     * @return array{
     *     status: int,
     *     headers: array<string, string>,
     *     body: string
     * }
     */
    public static function request(
        string $url,
        string $method = 'GET',
        array $headers = [],
        bool $downloadBody = false
    ): array
    {
        $curl = curl_init($url);

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
}
