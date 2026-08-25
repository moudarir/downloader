<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Traits;

use Moudarir\Downloader\DownloadConfig;

trait StreamOutputTrait
{

    /**
     * Reads a PHP stream resource and outputs it to the client with optional rate limiting.
     *
     * @param mixed $stream Valid PHP stream resource
     * @param int $length Total bytes to send
     * @param int $start Starting offset byte
     * @param int $bytesPerSecond Max bandwidth in bytes/sec (0 = unlimited)
     * @param int $chunkSize Read buffer size in bytes
     */
    protected function outputStream(
        mixed $stream,
        int $length,
        int $start = 0,
        int $bytesPerSecond = 0,
        int $chunkSize = DownloadConfig::CHUNK_SIZE
    ): void
    {
        if ($length <= 0 || !is_resource($stream) || get_resource_type($stream) !== 'stream') {
            return;
        }

        $meta = stream_get_meta_data($stream);

        if ($meta['seekable']) {
            // Reset or set stream pointer offset even when $start is 0
            if (fseek($stream, $start) !== 0) {
                return;
            }
        } elseif ($start > 0) {
            // Non-seekable streams cannot satisfy non-zero range requests
            return;
        }

        $bytesRemaining = $length;
        $totalBytes = 0;
        $transferStartTime = hrtime(true);

        while ($bytesRemaining > 0 && !feof($stream)) {
            // Prevent disk I/O if client aborted initially or during previous chunk's flush()
            if (connection_aborted() === 1) {
                break;
            }

            $readSize = min($chunkSize, $bytesRemaining);
            $buffer = fread($stream, $readSize);

            if ($buffer === false || $buffer === '') {
                break;
            }

            $bytesRead = strlen($buffer);
            echo $buffer;

            // Push chunk to network socket to trigger SAPI connection status update
            flush();

            $bytesRemaining -= $bytesRead;

            // Apply rate limiting throttle if configured
            if ($bytesPerSecond > 0) {
                $totalBytes += $bytesRead;

                $expectedMicroseconds = ($totalBytes / $bytesPerSecond) * 1_000_000;
                $elapsedMicroseconds = (hrtime(true) - $transferStartTime) / 1_000;
                $sleepMicroseconds = (int) ($expectedMicroseconds - $elapsedMicroseconds);

                if ($sleepMicroseconds > 0) {
                    usleep($sleepMicroseconds);
                }
            }
        }
    }
}
