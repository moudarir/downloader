<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Traits;

use Moudarir\Downloader\DownloadConfig;

trait StreamOutputTrait
{
    /**
     * Reads a PHP stream resource and outputs it to the client in chunks.
     *
     * @param mixed $stream Valid PHP stream resource
     * @param int $length Total bytes to send
     * @param int $start Starting offset byte
     */
    protected function outputStream(mixed $stream, int $length, int $start = 0): void
    {
        if ($length <= 0) {
            return;
        }

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
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

        while ($bytesRemaining > 0 && !feof($stream)) {
            // Prevent disk I/O if client aborted initially or during previous chunk's flush()
            if (connection_aborted() === 1) {
                break;
            }

            $chunkSize = min(DownloadConfig::CHUNK_SIZE, $bytesRemaining);
            $buffer = fread($stream, $chunkSize);

            if ($buffer === false || $buffer === '') {
                break;
            }

            echo $buffer;

            // Push chunk to network socket to trigger SAPI connection status update
            flush();

            $bytesRemaining -= strlen($buffer);
        }
    }
}
