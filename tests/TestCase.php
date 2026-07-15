<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Tests;

use Illuminate\Foundation\Application;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use LatentKit\Laravel\ClientFactory;
use LatentKit\Laravel\LatentKitServiceProvider;
use LatentKit\LatentKit;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected MockTransport $transport;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LatentKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.default', 'latentkit');
        $app['config']->set('latentkit.api_key', 'test-key');
        $app['config']->set('latentkit.base_url', 'https://example.test');
        $app['config']->set('latentkit.timeout', 120);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new MockTransport;
        $this->app->instance(
            ClientFactory::class,
            new ClientFactory($this->app['config'], $this->transport),
        );
        $this->app->forgetInstance(LatentKit::class);
        Ai::forgetInstance('latentkit');
    }

    protected function tearDown(): void
    {
        Ai::forgetInstance('latentkit');
        parent::tearDown();
    }

    protected function getApplicationTimezone($app): string
    {
        return 'UTC';
    }

    protected function resolveApplication(): Application
    {
        return parent::resolveApplication();
    }
}
