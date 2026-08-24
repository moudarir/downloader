# Downloader

A lightweight and modern PHP library for streaming files and in-memory data with full support for HTTP conditional requests and byte range requests.


## Features

- PHP 8.4+
- File downloads
- In-memory data downloads
- Bandwidth Rate Limiting
- HTTP Range Requests (RFC 9110)
- Multipart byte ranges
- Mandatory ETag generation
- Last-Modified support
- Conditional requests
- HEAD requests
- X-Sendfile support
- X-Accel-Redirect support
- UTF-8 filenames (RFC 6266)


## Requirements

- PHP 8.4+
- (optional) `mod_xsendfile` Apache module to download files with `ResponseAction::X_SEND_FILE` method. 


## Installation

```bash
composer require moudarir/downloader
```


## Usage

> `$mime` accepts:
> - `true` to automatically detect the MIME type from the file contents.
> - A non-empty string to explicitly specify the MIME type.
> - An empty string to use `DownloadConfig::DEFAULT_MIME`.


### Download a file

```php
$response = Download::fromFile('/path/to/file.pdf', mime: 'application/pdf');

$response->send();
```


### Download with a custom filename

```php
$response = Download::fromFile('/path/to/file.pdf', 'document.pdf', 'application/pdf');

$response->send();
```


### Inline response

```php
$response = Download::fromFile('/path/to/file.pdf', mime: 'application/pdf')->inline();

$response->send();
```


### Partial content / Range requests

```php
$response = Download::fromFile('/path/to/video.mp4', 'video.mp4', 'video/mp4', ResponseAction::PARTIAL)
    ->inline();

$response->send();
```

`ResponseAction::PARTIAL` enables HTTP Range request handling, including:

* full responses when no valid Range request is processed;
* single-range responses;
* multipart byte-range responses;
* unsatisfiable ranges (`416 Range Not Satisfiable`).

> For browser-based media playback, use `ResponseAction::PARTIAL` together with `inline()`.


### Download from data

```php
$response = Download::fromData($data, 'document.txt', 'text/plain');

$response->send();
```


### Server-side file delivery

For files, the response can be delegated to the web server using `X-Sendfile` or `X-Accel-Redirect`.

#### Apache – `X-Sendfile`

```php
use Moudarir\Downloader\Download;
use Moudarir\Downloader\Enums\ResponseAction;

$response = Download::fromFile('/path/to/file.pdf', responseAction: ResponseAction::X_SEND_FILE);

$response->send();
```

#### Nginx – `X-Accel-Redirect`

```php
use Moudarir\Downloader\Download;
use Moudarir\Downloader\Enums\ResponseAction;

$response = Download::fromFile(
    '/path/to/file.pdf',
    responseAction: ResponseAction::X_ACCEL_REDIRECT,
    xAccelRedirectUri: '/protected/path/to/file.pdf'
);

$response->send();
```

`X-Accel-Redirect` uses an internal URI configured for the web server. It is not required to be the physical filesystem path.

Server-side response actions are only available for file resources.


### Download configuration

Download behavior can be customized through `DownloadConfig`.

```php
use Moudarir\Downloader\Download;
use Moudarir\Downloader\DownloadConfig;

$config = new DownloadConfig()
    ->withLimitRate(500 * 1024)
    ->withChunkSize(256 * 1024)
    ->withMaxRangeItems(5);

$response = Download::fromFile(
    '/path/to/video.mp4',
    'video.mp4',
    'video/mp4',
    config: $config,
);

$response->send();
```

The configuration options are:

| Action                | Default                                            | Description                                                                     |
|:----------------------|:---------------------------------------------------|:--------------------------------------------------------------------------------|
| `withLimitRate()`     | `DownloadConfig::BYTES_PER_SECOND` (0 = unlimited) | Sets the maximum download rate in bytes per second.                             |
| `withChunkSize()`     | `DownloadConfig::CHUNK_SIZE` (128 KiB)             | Sets the stream read buffer size.                                               |
| `withMaxRangeItems()` | `DownloadConfig::MAX_RANGE_ITEMS` (10)             | Sets the maximum number of byte ranges accepted in a single HTTP Range request. |

> Invalid configuration values throw a `DownloadException`.


### Response actions

`Download::fromFile()` and `Download::fromData()` accept a `ResponseAction` to explicitly define the response mode.

Available actions:

| Action                             | Description                                             |
|:-----------------------------------|:--------------------------------------------------------|
| `ResponseAction::DEFAULT`          | Standard response                                       |
| `ResponseAction::PARTIAL`          | HTTP Range request handling                             |
| `ResponseAction::X_SEND_FILE`      | Delegate file delivery to the web server                |
| `ResponseAction::X_ACCEL_REDIRECT` | Delegate file delivery through Nginx `X-Accel-Redirect` |

The resulting `DownloadResponse` must always be sent explicitly:

```php
$response->send();
```


### Response metadata

`Download::fromFile()` and `Download::fromData()` return a `DownloadResponse` instance.

Response metadata can be inspected before sending the response through `DownloadResponse::metadata()`:

```php
use Moudarir\Downloader\Enums\StatusCode;

$response = Download::fromFile('/path/to/file.pdf', mime: 'application/pdf');

$metadata = $response->metadata();

echo $metadata->statusCode()->value;
echo $metadata->contentLength();
echo $metadata->contentType();
echo $metadata->filename();
echo $metadata->etagValue();

if ($metadata->statusCode() === StatusCode::OK) {
    // ...
}

$response->send();
```

Available metadata includes:

* resource information such as filepath, filename, filesize, MIME type and Last-Modified;
* Content-Length and Content-Type;
* the selected ResponseAction;
* ETag value and weak/strong state;
* Range information, including single and multipart ranges.
* `statusCode()` returns a `StatusCode` enum.

## ETag

ETag generation is mandatory.

The library automatically generates an ETag for every supported resource and uses it for HTTP conditional requests.

Available strategies:

* `ETagStrategy::MTIME`
* `ETagStrategy::INODE`
* `ETagStrategy::MD5`
* `ETagStrategy::SHA256`
* `ETagStrategy::SHA512`

A strategy can optionally be selected when creating a download:

```php
use Moudarir\Downloader\Download;
use Moudarir\Downloader\Enums\ETagStrategy;

$response = Download::fromFile('/path/to/file.pdf', strategy: ETagStrategy::SHA256);

$response->send();
```

If no strategy is specified, the library automatically selects the first strategy supported by the resource.

The selected strategy must be supported by the resource. Otherwise, the library throws a `DownloadException` exception.


## HTTP Features

The library automatically supports:

- ETag
- Last-Modified
- If-Match
- If-None-Match
- If-Modified-Since
- If-Unmodified-Since
- Range
- If-Range
- HEAD

Conditional request evaluation follows the precedence defined by `RFC 9110`.

`If-Range` supports both strong ETag comparison and HTTP-date comparison.

HTTP-date parsing supports the HTTP-date formats defined by `RFC 9110`.


## HTTP Responses

The library supports the following response status codes:

* `StatusCode::OK` (`200 OK`)
* `StatusCode::PARTIAL_CONTENT` (`206 Partial Content`)
* `StatusCode::NOT_MODIFIED` (`304 Not Modified`)
* `StatusCode::PRECONDITION_FAILED` (`412 Precondition Failed`)
* `StatusCode::RANGE_NOT_SATISFIABLE` (`416 Range Not Satisfiable`)

`412 Precondition Failed` responses do not include representation headers such as `Content-Type`, `Content-Disposition` or the representation `Content-Length`.


## License

MIT