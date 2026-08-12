<?php

declare(strict_types=1);

namespace Moudarir\Downloader;

use Moudarir\Downloader\Enums\ETagStrategy;
use Moudarir\Downloader\Enums\ResponseAction;
use Moudarir\Downloader\ETag\DownloadETag;
use Moudarir\Downloader\Exceptions\DownloadException;
use Moudarir\Downloader\Http\DownloadHeaders;
use Moudarir\Downloader\Http\DownloadPreconditions;
use Moudarir\Downloader\Http\DownloadRequest;
use Moudarir\Downloader\Http\DownloadResponse;
use Moudarir\Downloader\Resources\DownloadData;
use Moudarir\Downloader\Resources\DownloadFile;
use Moudarir\Downloader\Resources\DownloadResource;

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
        true|string $mime = '',
        ResponseAction $responseAction = ResponseAction::DEFAULT,
        ?string $xAccelRedirectUri = null,
        ?ETagStrategy $strategy = null,
    ): DownloadResponse
    {
        self::validateResponseAction($responseAction, true, $xAccelRedirectUri);

        $download = new self(
            DownloadFile::create($filepath, $filename, $mime),
            $responseAction,
            $strategy
        );

        return match ($responseAction) {
            ResponseAction::DEFAULT => $download->defaultResponse(),
            ResponseAction::PARTIAL => $download->partialResponse(),
            ResponseAction::X_SEND_FILE => $download->xSendFileResponse(),
            ResponseAction::X_ACCEL_REDIRECT => $download->xAccelRedirectResponse($xAccelRedirectUri),
        };
    }

    /**
     * @throws DownloadException
     */
    public static function fromData(
        string $data,
        string $filename,
        ?string $mime = null,
        ResponseAction $responseAction = ResponseAction::DEFAULT,
        ?ETagStrategy $strategy = null
    ): DownloadResponse
    {
        self::validateResponseAction($responseAction);

        $download = new self(
            DownloadData::create($data, $filename, $mime),
            $responseAction,
            $strategy
        );

        if ($responseAction === ResponseAction::PARTIAL) {
            return $download->partialResponse();
        }

        return $download->defaultResponse();
    }

    /**
     * @throws DownloadException
     */
    private function defaultResponse(): DownloadResponse
    {
        $result = $this->evaluatePreconditions();

        if ($result !== null) {
            return $result;
        }

        return  DownloadResponse::create(
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag,
        );
    }

    /**
     * @throws DownloadException
     */
    private function partialResponse(): DownloadResponse
    {
        $result = $this->evaluatePreconditions();

        if ($result !== null) {
            return $result;
        }

        return  DownloadResponse::create(
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag,
        );
    }

    /**
     * @throws DownloadException
     */
    private function xSendFileResponse(): DownloadResponse
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Sendfile');
        }

        $result = $this->evaluatePreconditions();

        if ($result !== null) {
            return $result;
        }

        $this->headers->addHeader('X-Sendfile', $this->resource->getFilepath());

        return  DownloadResponse::create(
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag,
        );
    }

    /**
     * @throws DownloadException
     */
    private function xAccelRedirectResponse(string $internalUri): DownloadResponse
    {
        if ($this->resource->getFilepath() === null) {
            throw DownloadException::operationNotSupportedOnData('X-Accel-Redirect');
        }

        $result = $this->evaluatePreconditions();

        if ($result !== null) {
            return $result;
        }

        $this->headers->addHeader('X-Accel-Redirect', $internalUri);

        return  DownloadResponse::create(
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag,
        );
    }

    private function evaluatePreconditions(): ?DownloadResponse
    {
        $result = DownloadPreconditions::evaluate(
            $this->request,
            $this->resource,
            $this->etag
        );

        if ($result->isOk()) {
            return null;
        }

        return DownloadResponse::precondition(
            $result->getStatusCode(),
            $this->headers,
            $this->resource,
            $this->request,
            $this->responseAction,
            $this->etag
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
