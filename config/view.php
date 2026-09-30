<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Do not use realpath() here: on a fresh checkout/extraction the directory
    | may not exist yet, which makes realpath() return false and Blade throws
    | "Please provide a valid cache path". The deploy/setup step below creates
    | the directory when needed.
    |
    */
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

    'relative_hash' => false,
    'cache' => true,
    'compiled_extension' => 'php',
    'check_cache_timestamps' => true,
];
