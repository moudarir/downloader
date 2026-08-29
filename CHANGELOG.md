# Changelog

All notable changes to this project will be documented in this file.

---

## [3.2.0] - 2026-08-29

### Refactored

* The file metadata like `filesize`, `lastmodified` and `stream` resource are now got from `FileMetadata` (moudarir/mime-detector);
* The method `DownloadFile::output()` no longer create a `stream` resource, `FileMetadata::stream()` used instead;

### Updated

* Update tests to reflect made changes.

### Removed

* The method `FileHelper::detectMimeType()` is no longer used.

## [3.1.2] - 2026-08-25

### Refactored

* Moved HTTP precondition evaluation from `Download` to `DownloadResponse` to centralize response construction and simplify `Download`.

## [3.1.1] - 2026-08-25

### Performance

* Improved download rate limiting accuracy by basing throttling delays on cumulative transfer time instead of calculating the delay independently for each chunk.

## [3.1.0] - 2026-08-24

### Added

* Added `DownloadConfig` as an immutable per-download configuration object.
* Added configurable download rate limiting through `withLimitRate()`.
* Added configurable stream chunk size through `withChunkSize()`.
* Added configurable maximum Range item count through `withMaxRangeItems()`.

### Changed

* `Download::fromFile()` and `Download::fromData()` now accept an optional `DownloadConfig`.
* Stream and Range configuration is now established before `DownloadResponse` creation.
* Propagated `DownloadConfig` through file, in-memory and multipart resource output.
* Updated examples and tests to use per-download configuration.

### Breaking Changes

* `DownloadResource::output()` now receives a `DownloadConfig` instance instead of `$bytesPerSecond` or `$chunkSize` parameters.
* Removed `DownloadResponse::limitRate()`.

## [3.0.0] - 2026-08-23

### Added

* Added download rate limiting through `DownloadResponse::limitRate()`.
* Added optional bandwidth limiting to file and in-memory resource streaming.
* Added rate limiting support for single-range and multipart byte-range responses.
* Added `X-Accel-Buffering: no` support to disable Nginx buffering during streamed responses.

### Changed

* Extended `DownloadResource::output()` with an optional maximum bandwidth parameter.
* Centralized rate-limited stream output through `StreamOutputTrait`.
* Improved client disconnection handling during streamed transfers.
* Updated `DownloadMultipartResponse::output()` signature to propagate bandwidth throttling parameters.
* Refactored `DownloadResponse` internal state to maintain strict immutability while supporting fluent rate limiting.

### Breaking Changes

* Changed the `DownloadResource::output()` method signature to accept an optional `$bytesPerSecond` parameter.
* Custom `DownloadResource` implementations must update their `output()` method signature accordingly.

### Usage

```php
$response = Download::fromFile('/path/to/video.mp4', 'video.mp4', 'video/mp4')
    ->limitRate(500 * 1024)
    ->send();
```

## [2.4.0] - 2026-08-20

### Added

* Added `FileHelper` to centralize file-related operations such as MIME type detection and `Content-Disposition` formatting.
* Added `StreamOutputTrait` to centralize stream output handling for file and in-memory resources.
* Added shared test infrastructure for integration resources, HTTP requests and test configuration.

### Changed

* Centralized supported HTTP header names in `DownloadConfig::VALID_HEADERS`.
* Improved HTTP header name normalization and validation through `CommonHelper::validateHeaderName()`.
* Improved multipart response streaming by flushing multipart sections as they are emitted.
* Improved client disconnection handling during streamed output.
* Extended the integration test environment with reusable file and data fixtures.
* Renamed HTTP file integration examples and tests to distinguish file resources from in-memory data resources.

### Refactored

* Refactored `DownloadFile` and `DownloadData` to share common stream output handling through `StreamOutputTrait`.
* Replaced hardcoded HTTP status codes with the typed `StatusCode` enum throughout the library and tests.
* Renamed `DownloadRangeItemStatus` to `RangeItemStatus`.


## [2.3.0] - 2026-08-16

### Added

* Added `DownloadResponse::metadata()` to inspect response metadata before sending the response.
* Added `MetadataHelper` to expose resource, response, ETag and Range metadata.
* Added `composer test`, `composer test:unit` and `composer test:integration` commands for the PHPUnit test suite.
* Added HTTP integration examples under `examples/`.

### Changed

* Refactored filename sanitization to use deterministic accent removal instead of `ext-iconv`.
* Removed the `ext-iconv` dependency.
* Improved RFC 9110 HTTP-date parsing, including RFC 850 year resolution and validation of HTTP date formats.
* Improved hash handling for invalid algorithms in file and in-memory resources.


## [2.2.0] - 2026-08-12

### Breaking Changes

* Changed the third parameter of `Download::fromFile()` from `bool $detectMime` to `string|true $mime`.
* `$mime` now accepts:
  - `true` to automatically detect the MIME type from the file contents.
  - A non-empty string to explicitly specify the MIME type.
  - An empty string to use `DownloadConfig::DEFAULT_MIME`.

### Performance

Avoid unnecessary MIME type detection when the MIME type is already known, reducing the overhead of file downloads, especially for large files and partial/range responses.

### Migration

Before:

```php
Download::fromFile($filepath, $filename, false);

// or
Download::fromFile($filepath, $filename, true);
```

After:

```php
// DownloadConfig::DEFAULT_MIME used
Download::fromFile($filepath, $filename);

// or, 'video/x-matroska' MIME type used
Download::fromFile($filepath, $filename, 'video/x-matroska');

// or, automatically detect the MIME type
Download::fromFile($filepath, $filename, true);
```


## [2.1.0] - 2026-08-12

### Breaking Changes

* Replaced the previous `stream*()` API with explicit response actions through `ResponseAction`.
* `Download::fromFile()` and `Download::fromData()` now return a `DownloadResponse` directly.
* Responses must be sent explicitly with `DownloadResponse::send()`.
* ETag generation is now mandatory for every download resource.

### Added

Added `ResponseAction` to explicitly define how a response is delivered:
* `DEFAULT`
* `PARTIAL`
* `X_SEND_FILE`
* `X_ACCEL_REDIRECT`

### Changed

* Refactored response creation and delivery around `DownloadResponse`.
* `X-Sendfile` and `X-Accel-Redirect` are now explicit response actions.
* Improved HTTP precondition precedence and evaluation according to `RFC 9110`.
* Improved `If-Range` handling for both entity tags and HTTP dates.
* Improved handling of `206 Partial Content` and `416 Range Not Satisfiable`.
* Improved multipart byte-range response handling.

### Fixed

* Fixed `ETag` header name normalization.
* Fixed `If-Modified-Since` evaluation.
* Fixed precedence between `If-None-Match` and `If-Modified-Since`.
* Fixed `If-Match: *` handling.
* Fixed `If-None-Match: *` handling.
* Fixed strong and weak ETag comparison for conditional requests.
* Fixed `If-Range` entity-tag comparison.
* Fixed `If-Range` HTTP-date comparison.
* Fixed `412 Precondition Failed` responses from including representation-specific headers.
* Fixed invalid and unsatisfiable Range request handling.


## [2.0.1] - 2026-08-10

### Changed

* Refactored HTTP header validation and multipart response handling.
* Improved HTTP Range and conditional request handling.

### Fixed

* Fixed ETag header name normalization.
* Fixed handling of invalid and unsatisfiable Range requests.
* Fixed HTTP precondition evaluation and precedence.
* Fixed multipart boundary generation failures.


## [2.0.0] - 2026-08-09

### Breaking Changes

* `stream()`, `streamPartial()`, `streamXSendFile()` and `streamXAccelRedirect()` now return a `DownloadResponse` object.
* A response must now be sent explicitly by calling `DownloadResponse::send()`.

### Added

* Added `DownloadResponse` to centralize HTTP response handling.
* Added HTTP header validation and protection against header injection.
* Added ASCII fallback generation for `Content-Disposition` filenames.
* Added `ext-iconv` as a dependency.

### Changed

* Refactored response construction and output handling from `Download` into `DownloadResponse`.
* Improved handling of full and partial content responses.
* Improved HTTP Range response handling, including `206 Partial Content` and `416 Range Not Satisfiable`.
* Improved ETag and conditional request handling.
* Refactored HTTP header management through `DownloadHeaders`.
* Updated documentation and usage examples to reflect the new response workflow.

### Fixed

* Improved handling of HTTP headers and `Content-Disposition` filenames.
* Fixed potential HTTP header injection through custom header values.
* Improved handling of filenames containing non-ASCII characters.


## [1.0.0] - 2026-08-07

Initial stable release.

### Added

- File download support.
- In-memory download support.
- HTTP Range Requests.
- Multipart byte range responses.
- Conditional requests support.
- ETag generation.
- Last-Modified support.
- X-Sendfile support.
- X-Accel-Redirect support.
- UTF-8 filename support (RFC 6266).
- HTTP-date parser supporting all RFC 9110 date formats.