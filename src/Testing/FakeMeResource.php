<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Testing;

use LatentKit\Responses\ConnectionContext;

final class FakeMeResource
{
    /** @var list<string> */
    private array $requests = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses = []) {}

    /** @return array<string, mixed> */
    public function retrieve(): array
    {
        $this->requests[] = 'retrieve';

        return array_shift($this->responses) ?? [];
    }

    public function retrieveContext(): ConnectionContext
    {
        return ConnectionContext::fromArray($this->retrieve());
    }

    /** @return list<string> */
    public function requests(): array
    {
        return $this->requests;
    }
}
