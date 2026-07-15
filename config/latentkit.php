<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Runtime app credential
    |--------------------------------------------------------------------------
    |
    | Use an app-scoped LatentKit runtime key. The value is read while Laravel
    | builds its configuration cache and must never be committed to source.
    |
    */
    'api_key' => env('LATENTKIT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gateway connection
    |--------------------------------------------------------------------------
    */
    'base_url' => env('LATENTKIT_BASE_URL', 'https://ai.latentkit.com'),
    'timeout' => (float) env('LATENTKIT_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Optional response profile
    |--------------------------------------------------------------------------
    |
    | This selects a gateway response profile, not a provider, model, or route.
    |
    */
    'response_profile' => env('LATENTKIT_RESPONSE_PROFILE'),
];
