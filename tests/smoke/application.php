<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Jobs\InvokeAgent;
use LatentKit\Laravel\LatentKitServiceProvider;
use LatentKit\LatentKit;

$applicationPath = $argv[1] ?? null;
if (! is_string($applicationPath) || $applicationPath === '') {
    fwrite(STDERR, "A clean Laravel application path is required.\n");
    exit(1);
}

require $applicationPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require $applicationPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->configurationIsCached()) {
    throw new RuntimeException('The clean application configuration is not cached.');
}

if (! $app->providerIsLoaded(LatentKitServiceProvider::class)) {
    throw new RuntimeException('Composer package discovery did not load LatentKitServiceProvider.');
}

if ($app['config']->get('latentkit.api_key') !== 'lk_clean_application_smoke') {
    throw new RuntimeException('The cached LatentKit API key configuration was not loaded.');
}

$direct = $app->make(LatentKit::class)->chat->create([
    ['role' => 'user', 'content' => 'Direct clean application smoke'],
]);
if (($direct['content'] ?? null) !== 'Clean application response') {
    throw new RuntimeException('Direct SDK injection did not return the fixture response.');
}

$response = (new AnonymousAgent('Answer briefly.', [], []))->prompt(
    'Laravel AI clean application smoke',
    provider: 'latentkit',
);
if ($response->text !== 'Clean application response') {
    throw new RuntimeException('The Laravel AI provider did not return the fixture response.');
}

$app->make(Dispatcher::class)->dispatch(new InvokeAgent(
    agent: new AnonymousAgent('Answer briefly.', [], []),
    prompt: 'Queued clean application smoke',
    provider: 'latentkit',
));

fwrite(STDOUT, "Clean application bindings and prompt smoke passed.\n");
