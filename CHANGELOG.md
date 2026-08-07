# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog
and this project follows Semantic Versioning.

---

## [1.0.0] - 2026-08-07

### Added

- Initial public release.
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

### Changed

- RFC 9110 compliant conditional request evaluation.
- Improved MIME type detection.
- Improved HTTP header generation.
- Better ETag comparison logic.
- Default Cache-Control handling.

### Fixed

- 304 responses no longer include entity headers.
- Strict HTTP-date parsing.
- Correct Content-Disposition generation.
- Various RFC compliance improvements.