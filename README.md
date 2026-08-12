# Downloader

A lightweight and modern PHP library for streaming files and in-memory data with full support for HTTP conditional requests and byte range requests.


## Features

- PHP 8.4+
- File downloads
- In-memory data downloads
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
- `ext-iconv`
- (optional) `mod_xsendfile` Apache module to download files with `ResponseAction::X_SEND_FILE` method. 


## Installation

```bash
composer require moudarir/downloader
```


## Usage


### Download a file

```php
$response = Download::fromFile('/path/to/file.pdf');

$response->send();
```


### Download with a custom filename

```php
$response = Download::fromFile('/path/to/file.pdf', 'document.pdf');

$response->send();
```


### Inline response

```php
$response = Download::fromFile('/path/to/file.pdf')->inline();

$response->send();
```


### Partial content / Range requests

```php
$response = Download::fromFile('/path/to/video.mp4', 'video.mp4', true, ResponseAction::PARTIAL)
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

The library handles the following response statuses:

* `200 OK`
* `206 Partial Content`
* `304 Not Modified`
* `412 Precondition Failed`
* `416 Range Not Satisfiable`

`412 Precondition Failed` responses do not include representation headers such as `Content-Type`, `Content-Disposition` or the representation `Content-Length`.


## License

MIT