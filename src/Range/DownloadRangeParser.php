<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

use Moudarir\Downloader\DownloadConfig;

final class DownloadRangeParser
{

    public static function parse(string $header, int $filesize): ?DownloadRange
    {
        if (!str_starts_with($header, 'bytes=')) {
            return null;
        }

        $ranges = explode(',', substr($header, 6));

        if (count($ranges) > DownloadConfig::MAX_RANGE_ITEMS) {
            return null;
        }

        $items = [];

        foreach ($ranges as $range) {
            if (($item = self::parseItem(trim($range), $filesize)) === null) {
                return null;
            }

            $items[] = $item;
        }

        if ($items === []) {
            return null;
        }

        $items = self::mergeRanges($items);
        $boundary = count($items) > 1 ? self::generateBoundary() : null;

        return DownloadRange::partial($items, $boundary);
    }

    private static function parseItem(string $range, int $filesize): ?DownloadRangeItem
    {
        if (!preg_match('/^(\d*)-(\d*)$/', $range, $matches)) {
            return null;
        }

        [, $start, $end] = $matches;

        /*
         * "-"
         */

        if ($start === '' && $end === '') {
            return null;
        }

        /*
         * "-500"
         */

        if ($start === '') {
            $suffix = (int) $end;

            if ($suffix <= 0) {
                return null;
            }

            $suffix = min($suffix, $filesize);

            return new DownloadRangeItem($filesize - $suffix, $filesize - 1);
        }

        $start = (int) $start;

        /*
         * start >= filesize
         */

        if ($start >= $filesize) {
            return null;
        }

        /*
         * "500-"
         */

        if ($end === '') {
            return new DownloadRangeItem($start, $filesize - 1);
        }

        $end = min((int) $end, $filesize - 1);

        /*
         * 600-500
         */

        if ($end < $start) {
            return null;
        }

        return new DownloadRangeItem($start, $end);
    }

    /**
     * @param list<DownloadRangeItem> $items
     * @return list<DownloadRangeItem>
     */
    private static function mergeRanges(array $items): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        usort(
            $items,
            static fn (DownloadRangeItem $a, DownloadRangeItem $b) => $a->getStart() <=> $b->getStart()
        );

        $merged = [];
        $current = $items[0];
        $count = count($items);

        for ($i = 1; $i < $count; $i++) {
            $next = $items[$i];

            if ($next->getStart() <= $current->getEnd() + 1) {
                $end = max($current->getEnd(), $next->getEnd());
                $current = new DownloadRangeItem($current->getStart(), $end);
                continue;
            }

            $merged[] = $current;
            $current = $next;
        }

        $merged[] = $current;
        return $merged;
    }

    private static function generateBoundary(): ?string
    {
        try {
            return bin2hex( random_bytes(16));
        } catch (\Random\RandomException $exception) {
            log_exception($exception);
            return null;
        }
    }
}
