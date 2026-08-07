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
- No external dependencies

---

## Installation

```bash
composer require moudarir/downloader
```

---

## Download a file

```php
use Moudarir\Downloader\Download;

Download::fromFile('/path/to/file.pdf')
    ->stream();
```

---

## Download in-memory data

```php
Download::fromData($content, 'report.csv', 'text/csv')
    ->stream();
```

---

## Inline display

```php
Download::fromFile($file)
    ->inline()
    ->stream();
```

---

## Custom headers

```php
Download::fromFile($file)
    ->addHeader('Cache-Control', 'public, max-age=3600')
    ->stream();
```

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