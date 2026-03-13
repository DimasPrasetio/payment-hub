<?php

$releaseVersion = trim((string) @file_get_contents(base_path('VERSION')));

return [
    /*
    |--------------------------------------------------------------------------
    | Release Version
    |--------------------------------------------------------------------------
    |
    | This is the application release version that maps to git tags such as
    | "v1.0.0". It is intentionally separate from the API version so internal
    | improvements can be released without forcing an API version bump.
    |
    */
    'release' => $releaseVersion !== '' ? $releaseVersion : '0.0.0',

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | This is the public API contract version exposed under /api/{version}.
    | Only change this when the external API contract introduces breaking
    | changes that justify a new versioned route namespace.
    |
    */
    'api' => [
        'current' => 'v1',
    ],
];
