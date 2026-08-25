# Download endpoint

`file.php` and `file-partial.php` are minimal HTTP endpoints used to demonstrate the library and to run the HTTP integration tests.

They serve the local fixture:

```text
examples/resources/video.mov
```

The endpoints are available at:

```text
http://127.0.0.1:8080/file.php
and
http://127.0.0.1:8080/file-partial.php
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

The `file-partial.php` endpoint uses `ResponseAction::PARTIAL` and `inline()` so that range requests and video streaming can be tested through a real HTTP server.
