<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadHeaders;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Http\DownloadResponse;
use Moudarir\Downloader\Resources\DownloadData;
use Moudarir\Downloader\Resources\DownloadFile;
use Moudarir\Downloader\Resources\DownloadResource;
use Moudarir\File\Enum\MimeType;

final readonly class Download
{

    private DownloadHeaders $headers;

    private DownloadETag $etag;

    private DownloadRequest $request;

    /**
     * @throws DownloadException
     */
    private function __construct(
        private DownloadResource $resource,
        private ResponseAction $responseAction,
        private DownloadConfig $config,
        ?ETagStrategy $strategy = null,
    )
    {
        $this->request = DownloadRequest::create();

        $this->etag = DownloadETag::create($this->resource, $strategy);

        $this->headers = new DownloadHeaders();
    }

    /**
     * @throws DownloadException
     */
    public static function fromFile(
        string $filepath,
        ?string $filename = null,
        ?MimeType $mime = null,
        ResponseAction $responseAction = ResponseAction::DEFAULT,
        ?string $xAccelRedirectUri = null,
        ?ETagStrategy $strategy = null,
        DownloadConfig $config = new DownloadConfig(),
    ): DownloadResponse
    {
        self::validateResponseAction($responseAction, true, $xAccelRedirectUri);

        return new self(
            DownloadFile::create($filepath, $filename, $mime, $xAccelRedirectUri),
            $responseAction,
            $config,
            $strategy
        )
            ->response();
    }

    /**
     * @throws DownloadException
     */
    public static function fromData(
        string $data,
        string $filename,
        ?string $mime = null,
        ResponseAction $responseAction = ResponseAction::DEFAULT,
        ?ETagStrategy $strategy = null,
        DownloadConfig $config = new DownloadConfig(),
    ): DownloadResponse
    {
        self::validateResponseAction($responseAction);

        return new self(
            DownloadData::create($data, $filename, $mime),
            $responseAction,
            $config,
            $strategy
        )
            ->response();
    }

    /**
     * @throws DownloadException
     */
    private function response(): DownloadResponse
    {
        if ($this->responseAction->isServerSide() === true && $this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData($this->responseAction->value);
        }

        return DownloadResponse::create(
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag,
            $this->config,
        );
    }

    /**
     * @throws DownloadException
     */
    private static function validateResponseAction(
        ResponseAction $responseAction,
        bool $supportsServerSide = false,
        ?string $xAccelRedirectUri = null,
    ): void
    {
        if (
            $responseAction === ResponseAction::X_ACCEL_REDIRECT
            && ($xAccelRedirectUri === null || trim($xAccelRedirectUri) === '')
        ) {
            throw DownloadException::requiredInternalUri();
        }

        if ($supportsServerSide === false && $responseAction->isServerSide()) {
            throw DownloadException::operationNotSupportedOnData($responseAction->value);
        }
    }
}
