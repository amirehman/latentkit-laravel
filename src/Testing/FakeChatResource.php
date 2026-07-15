<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Testing;

final class FakeChatResource
{
    /** @var list<array<string, mixed>> */
    private array $requests = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses = []) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $extraBody
     * @return array<string, mixed>
     */
    public function create(
        array $messages,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
    ): array {
        $this->requests[] = compact('messages', 'maxTokens', 'temperature', 'responseProfile', 'extraBody');

        return array_shift($this->responses) ?? ['content' => 'LatentKit fake response'];
    }

    /** @return list<array<string, mixed>> */
    public function requests(): array
    {
        return $this->requests;
    }
}
