<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

final readonly class DownloadRangeItem
{
    public function __construct(private int $start, private int $end)
    {
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function getLength(): int
    {
        return ($this->end - $this->start) + 1;
    }
}
