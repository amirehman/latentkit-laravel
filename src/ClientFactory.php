<?php

declare(strict_types=1);

namespace LatentKit\Laravel;

use Illuminate\Contracts\Config\Repository;
use LatentKit\LatentKit;
use LatentKit\Transport\TransportInterface;

final class ClientFactory
{
    public function __construct(
        private readonly Repository $config,
        private readonly ?TransportInterface $transport = null,
    ) {}

    public function make(?float $timeoutSeconds = null): LatentKit
    {
        $configuredTimeout = $this->config->get('latentkit.timeout', 120);
        $timeout = $timeoutSeconds ?? (is_numeric($configuredTimeout) ? (float) $configuredTimeout : 120.0);

        return new LatentKit(
            apiKey: $this->optionalString($this->config->get('latentkit.api_key')),
            baseUrl: $this->optionalString($this->config->get('latentkit.base_url')),
            timeoutSeconds: $timeout,
            transport: $this->transport,
            toolSlug: LatentKitServiceProvider::TOOL_SLUG,
            toolVersion: LatentKitServiceProvider::VERSION,
        );
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
