<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Testing;

use RuntimeException;

final class LatentKitFake
{
    public readonly FakeChatResource $chat;

    public readonly FakeMeResource $me;

    /**
     * @param  list<array<string, mixed>>  $chatResponses
     * @param  list<array<string, mixed>>  $meResponses
     */
    public function __construct(array $chatResponses = [], array $meResponses = [])
    {
        $this->chat = new FakeChatResource($chatResponses);
        $this->me = new FakeMeResource($meResponses);
    }

    public function assertChatSent(?callable $callback = null): void
    {
        foreach ($this->chat->requests() as $request) {
            if ($callback === null || $callback($request) === true) {
                return;
            }
        }

        throw new RuntimeException('No matching LatentKit chat request was recorded.');
    }

    public function assertNothingSent(): void
    {
        if ($this->chat->requests() !== [] || $this->me->requests() !== []) {
            throw new RuntimeException('Unexpected LatentKit requests were recorded.');
        }
    }
}
