<?php

declare(strict_types=1);

namespace Moudarir\Downloader\ETag;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Resources\DownloadResource;

final class DownloadETagResolver
{

    /**
     * @throws DownloadException
     */
    public static function resolve(DownloadResource $resource, ?ETagStrategy $strategy = null): ETagStrategy
    {
        $strategies = $resource->getSupportedETagStrategies();

        if ($strategies === []) {
            throw DownloadException::noETagStrategySupported($resource::class);
        }

        if ($strategy !== null && in_array($strategy, $strategies, true)) {
            return $strategy;
        }

        return $strategies[0];
    }
}
