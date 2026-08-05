<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

final readonly class DownloadRange
{

    /**
     * @param list<DownloadRangeItem> $items
     */
    private function __construct(private array $items, private bool $partial, private ?string $boundary = null)
    {
    }

    public static function full(int $filesize): self
    {
        return new self([new DownloadRangeItem(0, $filesize - 1)], false);
    }

    /**
     * @param list<DownloadRangeItem> $items
     */
    public static function partial(array $items, ?string $boundary): self
    {
        return new self($items, true, $boundary);
    }

    /**
     * @return list<DownloadRangeItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getFirstItem(): DownloadRangeItem
    {
        return $this->items[0];
    }

    public function isPartial(): bool
    {
        return $this->partial;
    }

    public function isMultipart(): bool
    {
        return count($this->items) > 1;
    }

    public function getBoundary(): ?string
    {
        return $this->boundary;
    }
}
