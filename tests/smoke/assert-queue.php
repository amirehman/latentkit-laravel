<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;

$applicationPath = $argv[1] ?? null;
if (! is_string($applicationPath) || $applicationPath === '') {
    fwrite(STDERR, "A clean Laravel application path is required.\n");
    exit(1);
}

require $applicationPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require $applicationPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = $app->make(ConnectionInterface::class);
$queued = $database->table('jobs')->count();
$failed = $database->table('failed_jobs')->count();

if ($queued !== 0 || $failed !== 0) {
    throw new RuntimeException(sprintf(
        'The queue worker did not finish cleanly: %d queued, %d failed.',
        $queued,
        $failed,
    ));
}

fwrite(STDOUT, "Clean application queue worker smoke passed.\n");
