<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use LatentKit\Laravel\Testing\LatentKitFake;

/** @see \LatentKit\LatentKit */
final class LatentKit extends Facade
{
    /**
     * Return the bound SDK client or its test fake.
     */
    public static function client(): \LatentKit\LatentKit|LatentKitFake
    {
        /** @var \LatentKit\LatentKit|LatentKitFake $client */
        $client = self::getFacadeRoot();

        return $client;
    }

    /**
     * Replace the SDK binding with a network-free fake.
     *
     * @param  list<array<string, mixed>>  $chatResponses
     * @param  list<array<string, mixed>>  $meResponses
     */
    public static function fake(array $chatResponses = [], array $meResponses = []): LatentKitFake
    {
        $fake = new LatentKitFake($chatResponses, $meResponses);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \LatentKit\LatentKit::class;
    }
}
