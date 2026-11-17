<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Exceptions;

use LatentKit\Exceptions\ApiException;
use RuntimeException;
use Throwable;

final class LatentKitAiException extends RuntimeException
{
    /**
     * @param  string|null  $apiCode  LatentKit error code such as KEY_DISABLED,
     *                                INSUFFICIENT_CREDITS, or RATE_LIMITED, when the response carried one.
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
        public readonly ?string $apiCode = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromApiException(ApiException $exception): self
    {
        $apiCode = self::safeErrorCode($exception->apiCode);
        $message = $apiCode !== null
            ? sprintf('LatentKit rejected the Laravel AI request with HTTP %d (%s).', $exception->statusCode, $apiCode)
            : sprintf('LatentKit rejected the Laravel AI request with HTTP %d.', $exception->statusCode);
        if ($exception->requestId !== null) {
            $message .= ' Request ID: '.$exception->requestId.'.';
        }

        return new self(
            message: $message,
            statusCode: $exception->statusCode,
            requestId: $exception->requestId,
            previous: $exception,
            apiCode: $apiCode,
        );
    }

    /**
     * Return a LatentKit error code that is safe to show and log.
     *
     * Error codes are a fixed vocabulary. Any other text in an error response
     * may carry upstream provider output, so a value that does not look like a
     * code is dropped instead of displayed.
     */
    public static function safeErrorCode(?string $code): ?string
    {
        return is_string($code) && preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $code) === 1
            ? strtoupper($code)
            : null;
    }
}
