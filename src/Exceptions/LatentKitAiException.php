<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Exceptions;

use LatentKit\Exceptions\ApiException;
use RuntimeException;
use Throwable;

final class LatentKitAiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromApiException(ApiException $exception): self
    {
        $message = sprintf('LatentKit rejected the Laravel AI request with HTTP %d.', $exception->statusCode);
        if ($exception->requestId !== null) {
            $message .= ' Request ID: '.$exception->requestId.'.';
        }

        return new self(
            message: $message,
            statusCode: $exception->statusCode,
            requestId: $exception->requestId,
            previous: $exception,
        );
    }
}
