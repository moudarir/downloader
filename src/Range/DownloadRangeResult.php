<?php
declare(strict_types=1);

namespace Moudarir\Downloader\Range;

final readonly class DownloadRangeResult
{
    private function __construct(
        private ?DownloadRange $range,
        private bool $valid,
    ) {
    }

    public static function invalid(): self
    {
        return new self(null, false);
    }

    public static function unsatisfiable(): self
    {
        return new self(null, true);
    }

    public static function valid(DownloadRange $range): self
    {
        return new self($range, true);
    }

    public function isInvalid(): bool
    {
        return !$this->valid;
    }

    public function isUnsatisfiable(): bool
    {
        return $this->valid && $this->range === null;
    }

    public function getRange(): ?DownloadRange
    {
        return $this->range;
    }
}
