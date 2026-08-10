<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Range;

use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\DownloadRangeItemStatus;
use Moudarir\Downloader\Exceptions\DownloadException;
use Random\RandomException;

final class DownloadRangeParser
{

    /**
     * @throws DownloadException
     */
    public static function parse(string $header, int $filesize): DownloadRangeResult
    {
        if (!str_starts_with($header, 'bytes=')) {
            return DownloadRangeResult::invalid();
        }

        $ranges = explode(',', substr($header, 6));

        if (count($ranges) > DownloadConfig::MAX_RANGE_ITEMS) {
            return DownloadRangeResult::invalid();
        }

        $items = [];

        foreach ($ranges as $range) {
            $result = self::parseItem(trim($range), $filesize);

            if ($result === DownloadRangeItemStatus::INVALID) {
                return DownloadRangeResult::invalid();
            }

            if ($result === DownloadRangeItemStatus::UNSATISFIABLE) {
                continue;
            }

            $items[] = $result;
        }

        if ($items === []) {
            return DownloadRangeResult::unsatisfiable();
        }

        $items = self::mergeRanges($items);
        $boundary = count($items) > 1 ? self::generateBoundary() : null;

        return DownloadRangeResult::valid(
            DownloadRange::partial($items, $boundary)
        );
    }

    private static function parseItem(string $range, int $filesize): DownloadRangeItem|DownloadRangeItemStatus
    {
        if (!preg_match('/^(\d*)-(\d*)$/', $range, $matches)) {
            return DownloadRangeItemStatus::INVALID;
        }

        [, $start, $end] = $matches;

        /*
         * "-"
         */
        if ($start === '' && $end === '') {
            return DownloadRangeItemStatus::INVALID;
        }

        /*
         * "-500"
         */
        if ($start === '') {
            $suffix = (int)$end;

            if ($suffix <= 0) {
                return DownloadRangeItemStatus::INVALID;
            }

            if ($filesize === 0) {
                return DownloadRangeItemStatus::UNSATISFIABLE;
            }

            $suffix = min($suffix, $filesize);

            return new DownloadRangeItem($filesize - $suffix, $filesize - 1);
        }

        $start = (int)$start;

        /*
         * start >= filesize
         */
        if ($start >= $filesize) {
            return DownloadRangeItemStatus::UNSATISFIABLE;
        }

        /*
         * "500-"
         */
        if ($end === '') {
            return new DownloadRangeItem($start, $filesize - 1);
        }

        $end = min((int)$end, $filesize - 1);

        /*
         * "600-500"
         */
        if ($end < $start) {
            return DownloadRangeItemStatus::INVALID;
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

    /**
     * @throws DownloadException
     */
    private static function generateBoundary(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (RandomException $exception) {
            throw DownloadException::boundaryGenerationFailed($exception->getMessage());
        }
    }
}
