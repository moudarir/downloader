<?php

use Moudarir\Downloader\Download;
use Moudarir\Downloader\DownloadConfig;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Tests\Support\TestConfig;

require_once 'bootstrap.php';

$resource = TestConfig::resourceFile('mov');
$filepath = RESOURCES_PATH . $resource['basename'];

try {
    if (!is_file($filepath)) {
        throw new RuntimeException(
            sprintf("Test fixture not found: `%s`.", $resource['basename'])
        );
    }

    $config = new DownloadConfig()
        ->withLimitRate(500 * 1024);

    Download::fromFile(
        $filepath,
        $resource['filename'],
        $resource['mime'],
        ResponseAction::PARTIAL,
        config: $config,
    )
        ->inline()
        ->send();
} catch (DownloadException|RuntimeException $exception) {
    error_log($exception->getMessage());
}
