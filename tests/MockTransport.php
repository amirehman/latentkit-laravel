<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Tests;

use LatentKit\Transport\HttpResponse;
use LatentKit\Transport\TransportInterface;
use RuntimeException;

final class MockTransport implements TransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: float}> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $responses = [];

    public function push(HttpResponse $response): void
    {
        $this->responses[] = $response;
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeoutSeconds,
        ];

        return array_shift($this->responses) ?? throw new RuntimeException('No mock response was queued.');
    }
}
