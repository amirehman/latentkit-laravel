<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Ai;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;
use LatentKit\Laravel\ClientFactory;

final class LatentKitProvider extends Provider implements TextProvider
{
    use GeneratesText;
    use HasTextGateway;
    use StreamsText;

    public const ROUTE_MODEL_ID = 'latentkit-route';

    /**
     * The client factory and response profile stay protected and writable:
     * Laravel AI restores a provider from a queue payload by resolving it
     * again by name and copying the properties its base class can reach.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected ClientFactory $clientFactory,
        protected array $config,
        protected Dispatcher $events,
        protected ?string $responseProfile = null,
    ) {}

    public function providerCredentials(): array
    {
        return [];
    }

    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new LatentKitTextGateway(
            $this->clientFactory,
            $this->responseProfile,
        );
    }

    public function defaultTextModel(): string
    {
        return self::ROUTE_MODEL_ID;
    }

    public function cheapestTextModel(): string
    {
        return self::ROUTE_MODEL_ID;
    }

    public function smartestTextModel(): string
    {
        return self::ROUTE_MODEL_ID;
    }
}
