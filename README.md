# Downloader

A lightweight and modern PHP library for streaming files and in-memory data with full support for HTTP conditional requests and byte range requests.

## Features

- PHP 8.4+
- File downloads
- In-memory data downloads
- HTTP Range Requests (RFC 9110)
- Multipart byte ranges
- ETag support
- Last-Modified support
- Conditional requests
- HEAD requests
- X-Sendfile support
- X-Accel-Redirect support
- UTF-8 filenames (RFC 6266)

---

## Requirements

- PHP 8.4+
- `ext-iconv`

---

## Installation

```bash
composer require moudarir/downloader
```

---

## Usage

### Download a file

```php
$response = Download::fromFile('/path/to/file.pdf')
    ->stream();

$response->send();
```

### Download with a custom filename

```php
$response = Download::fromFile('/path/to/file.pdf', 'document.pdf')
    ->stream();

$response->send();
```

### Inline response

```php
$response = Download::fromFile('/path/to/file.pdf')
    ->inline()
    ->stream();

$response->send();
```

### Partial content / Range requests

```php
$response = Download::fromFile('/path/to/video.mp4')
    ->inline()
    ->streamPartial();

$response->send();
```

`streamPartial()` supports HTTP Range requests and automatically handles full responses, single-range responses, multipart byte ranges, and unsatisfiable ranges.

> For browser-based media playback, use `inline()` together with `streamPartial()` to enable inline display and HTTP range requests.

### Download from data

```php
$response = Download::fromData(
    $data,
    'document.txt',
    'text/plain'
)->stream();

$response->send();
```

### Server-side file delivery

For files, the response can be delegated to the web server using `X-Sendfile` or `X-Accel-Redirect`.

```php
$response = Download::fromFile('/path/to/file.pdf')
    ->streamXSendFile();

$response->send();
```

Or with Nginx:

```php
$response = Download::fromFile('/path/to/file.pdf')
    ->streamXAccelRedirect('/protected/file.pdf');

$response->send();
```

> **Note:** `stream()`, `streamPartial()`, `streamXSendFile()` and `streamXAccelRedirect()` return a `DownloadResponse` object. The response must be sent explicitly using `send()`.

## What's new in 2.0

* Added `DownloadResponse` to centralize HTTP response construction and delivery.
* `stream()`, `streamPartial()`, `streamXSendFile()` and `streamXAccelRedirect()` now return a `DownloadResponse` object.
* Improved HTTP Range and conditional request handling.
* Improved ETag handling.
* Added HTTP header name and value validation.
* Improved `Content-Disposition` filename handling, including ASCII fallbacks for clients that do not support extended filename parameters.
* Added `ext-iconv` dependency.

---

## HTTP Features

The library automatically supports:

- ETag
- Last-Modified
- If-Match
- If-None-Match
- If-Modified-Since
- If-Unmodified-Since
- Range Requests
- If-Range
- HEAD requests

All conditional request evaluation follows RFC 9110.

---

## License

MIT