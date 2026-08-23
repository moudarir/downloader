<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Resources;

use Moudarir\Downloader\Enums\ETagStrategy;

interface DownloadResource
{

    public function getFilename(): string;
    public function getFilesize(): int;
    public function getMime(): string;
    public function getLastModified(): ?int;
    public function getInternalUri(): ?string;

    /**
     * Outputs the resource content.
     *
     * @param int $length Total bytes to send
     * @param int $start Starting byte offset
     * @param int $bytesPerSecond Maximum bandwidth limit in bytes/sec (0 = unlimited)
     */
    public function output(int $length, int $start = 0, int $bytesPerSecond = 0): void;
    public function getFilepath(): ?string;
    public function getHash(string $algorithm): ?string;

    /**
     * @return list<ETagStrategy>
     */
    public function getSupportedETagStrategies(): array;
}
