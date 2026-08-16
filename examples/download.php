<?php

use Moudarir\Downloader\Download;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\Exceptions\DownloadException;

require_once '../vendor/autoload.php';

try {
    Download::fromFile(
        '../public/files/video.mov',
        'video.mov',
        true
    )->send();
} catch (DownloadException $exception) {
    error_log($exception->getMessage());
}
