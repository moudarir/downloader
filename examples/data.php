<?php

use Moudarir\Downloader\Download;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Tests\Support\TestConfig;

require_once 'bootstrap.php';

$resource = TestConfig::resourceData();

try {
    Download::fromData(
        $resource['content'],
        $resource['filename'],
        $resource['mime']
    )->send();
} catch (DownloadException $exception) {
    error_log($exception->getMessage());
}
