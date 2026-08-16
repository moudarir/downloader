# Download endpoint

`download.php` and `download-partial.php` are minimal HTTP endpoints used to demonstrate the library and to run the HTTP integration tests.

They serve the local fixture:

```text
public/files/video.mov
```

The `public/files/` directory is intentionally excluded from Git because the test fixture is a local file.

The endpoints are available at:

```text
http://localhost:8080/download.php
and
http://localhost:8080/download-partial.php
```

Start the local PHP server with:

```shell
composer server-start
```

Stop it with:

```shell
composer server-stop
```

Run the HTTP integration tests with:

```shell
composer test:integration
```

The `download-partial.php` endpoint uses `ResponseAction::PARTIAL` and `inline()` so that range requests and video streaming can be tested through a real HTTP server.
