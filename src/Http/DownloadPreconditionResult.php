<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\StatusCode;

final readonly class DownloadPreconditionResult
{

    public function __construct(private StatusCode $statusCode)
    {
    }

    public static function proceed(): self
    {
        return new self(StatusCode::OK);
    }

    public static function notModified(): self
    {
        return new self(StatusCode::NOT_MODIFIED);
    }

    public static function preconditionFailed(): self
    {
        return new self(StatusCode::PRECONDITION_FAILED);
    }

    public function isOk(): bool
    {
        return $this->statusCode === StatusCode::OK;
    }

    public function getStatusCode(): StatusCode
    {
        return $this->statusCode;
    }

    public function getStatusCodeValue(): int
    {
        return $this->statusCode->value;
    }
}
