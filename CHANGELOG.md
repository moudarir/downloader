# Changelog

All notable changes to this project will be documented in this file.

---

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