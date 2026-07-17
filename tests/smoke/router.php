<?php

declare(strict_types=1);

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/health') {
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);

    return;
}

if ($path === '/v1/me') {
    readfile(__DIR__.'/../fixtures/me-context.json');

    return;
}

if ($path === '/v1/chat') {
    echo json_encode([
        'content' => 'Clean application response',
        'provider' => 'fixture-provider',
        'model' => 'fixture-model',
        'finish_reason' => 'stop',
        'usage' => [
            'prompt_tokens' => 4,
            'completion_tokens' => 3,
        ],
        'request_id' => 'req_clean_app',
    ], JSON_THROW_ON_ERROR);

    return;
}

http_response_code(404);
echo json_encode(['error' => 'Not found'], JSON_THROW_ON_ERROR);
