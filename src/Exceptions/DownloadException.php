<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Exceptions;

use Exception;

final class DownloadException extends Exception
{

    public static function filepathNotFound(): self
    {
        return new self("The specified file path was not found.");
    }

    public static function filesizeIssue(): self
    {
        return new self("Unable to determine the file size.");
    }

    public static function invalidHeaderName(string $name): self
    {
        return new self(sprintf("Invalid HTTP header name `%s`.", $name));
    }

    public static function invalidHeaderValue(string $value): self
    {
        return new self(
            sprintf("Invalid HTTP header value: `%s`.", $value)
        );
    }

    public static function eTagStrategyFailed(string $strategy): self
    {
        return new self(sprintf("Unable to generate an ETag using the `%s` strategy.", $strategy));
    }

    public static function emptyDataSource(): self
    {
        return new self("The data source cannot be empty.");
    }

    public static function filenameRequiredForData(): self
    {
        return new self("A filename is required when downloading data from memory.");
    }

    public static function operationNotSupportedOnData(string $operation): self
    {
        return new self(sprintf(
            "The `%s` operation is not supported for in-memory resources.",
            $operation
        ));
    }

    public static function noETagStrategySupported(string $resource): self
    {
        return new self(sprintf(
            "Resource `%s` does not declare any supported ETag strategy.",
            $resource
        ));
    }

    public static function unsupportedETagStrategy(string $strategy, string $resource): self
    {
        return new self(sprintf(
            "ETag strategy `%s` is not supported by resource `%s`.",
            $strategy, $resource
        ));
    }

    public static function unsupportedRequestMethod(string $method): self
    {
        return new self(sprintf(
            "The HTTP request method `%s` is not supported.",
            $method
        ));
    }

    public static function boundaryGenerationFailed(string $message): self
    {
        return new self("Unable to generate a multipart boundary: " . $message);
    }

    public static function requiredInternalUri(): self
    {
        return new self("An internal URI is required for X-Accel-Redirect.");
    }

    public static function invalidLimitRate(): self
    {
        return new self("Rate limit must be greater than or equal to 0.");
    }

    public static function generic(string $message): self
    {
        return new self($message);
    }
}
