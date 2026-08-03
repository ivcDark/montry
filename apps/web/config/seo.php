<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search engine indexing
    |--------------------------------------------------------------------------
    |
    | When false, robots.txt disallows all crawling and public pages emit
    | noindex. Defaults to true only in production.
    |
    */
    'indexable' => filter_var(
        env('SEO_INDEXABLE', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOL
    ),
];
