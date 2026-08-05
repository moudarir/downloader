<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Http;

use Moudarir\Downloader\Enums\PreconditionStatus;

final readonly class DownloadPreconditionResult
{

    public function __construct(private PreconditionStatus $status)
    {
    }

    public static function ok(): self
    {
        return new self(PreconditionStatus::OK);
    }

    public static function notModified(): self
    {
        return new self(PreconditionStatus::NOT_MODIFIED);
    }

    public static function preconditionFailed(): self
    {
        return new self(PreconditionStatus::PRECONDITION_FAILED);
    }

    public function isOk(): bool
    {
        return $this->status === PreconditionStatus::OK;
    }

    public function getStatus(): PreconditionStatus
    {
        return $this->status;
    }

    public function getStatusCode(): int
    {
        return $this->status->value;
    }
}
