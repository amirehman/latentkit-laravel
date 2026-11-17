<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Console;

use Illuminate\Console\Command;
use LatentKit\Exceptions\ApiException;
use LatentKit\Laravel\ClientFactory;
use LatentKit\Laravel\Exceptions\LatentKitAiException;
use Throwable;

final class CheckConnectionCommand extends Command
{
    protected $signature = 'latentkit:check';

    protected $description = 'Verify the configured LatentKit app connection and assigned route';

    public function handle(ClientFactory $clientFactory): int
    {
        try {
            $context = $clientFactory->make()->me->retrieveContext();
        } catch (ApiException $exception) {
            $apiCode = LatentKitAiException::safeErrorCode($exception->apiCode);
            $this->error(sprintf(
                'LatentKit rejected the connection check with HTTP %d%s.%s',
                $exception->statusCode,
                $apiCode !== null ? ' ('.$apiCode.')' : '',
                $exception->requestId !== null ? ' Request ID: '.$exception->requestId : '',
            ));

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('LatentKit could not complete the connection check. Review the server configuration and network access.');

            return self::FAILURE;
        }

        $this->info('LatentKit connection is healthy.');
        $this->table(['Context', 'Value'], [
            ['Workspace', $this->display($context->tenant?->name)],
            ['App', $this->display($context->app?->name)],
            ['Assigned route', $this->display($context->route?->name)],
            ['Route published', $context->route?->published === null ? 'Not available' : ($context->route->published ? 'Yes' : 'No')],
            ['Route models', $context->route?->modelCount === null ? 'Not available' : (string) $context->route->modelCount],
            ['Credits remaining', $context->credits?->remainingUsd === null ? 'Not available' : '$'.number_format($context->credits->remainingUsd, 2)],
            ['Latest winner', $context->latestRequest === null
                ? 'Not available'
                : trim($context->latestRequest->provider.' / '.$context->latestRequest->model, ' /')],
        ]);

        return self::SUCCESS;
    }

    private function display(?string $value): string
    {
        return $value !== null && trim($value) !== '' ? $value : 'Not available';
    }
}
