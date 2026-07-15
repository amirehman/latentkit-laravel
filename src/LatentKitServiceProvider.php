<?php

declare(strict_types=1);

namespace LatentKit\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use LatentKit\Laravel\Ai\LatentKitProvider;
use LatentKit\Laravel\Console\CheckConnectionCommand;
use LatentKit\LatentKit;

final class LatentKitServiceProvider extends ServiceProvider
{
    public const TOOL_SLUG = 'latentkit-laravel';

    public const VERSION = '0.1.0-alpha.1';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/latentkit.php', 'latentkit');

        $this->app->singleton(ClientFactory::class);
        $this->app->singleton(LatentKit::class, fn ($app): LatentKit => $app->make(ClientFactory::class)->make());

        $this->app->afterResolving(AiManager::class, function (AiManager $manager): void {
            $manager->extend('latentkit', function ($app, array $config): LatentKitProvider {
                $responseProfile = $config['response_profile'] ?? $app['config']->get('latentkit.response_profile');

                return new LatentKitProvider(
                    clientFactory: $app->make(ClientFactory::class),
                    config: $config,
                    events: $app->make(Dispatcher::class),
                    responseProfile: is_string($responseProfile) && trim($responseProfile) !== ''
                        ? trim($responseProfile)
                        : null,
                );
            });
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CheckConnectionCommand::class]);
            $this->publishes([
                __DIR__.'/../config/latentkit.php' => config_path('latentkit.php'),
            ], 'latentkit-config');
        }
    }
}
