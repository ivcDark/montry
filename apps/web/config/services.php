<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'poller' => [
        'base_url' => env('POLLER_BASE_URL'),
        'token' => env('POLLER_TOKEN'),
        'internal_token' => env('POLLER_INTERNAL_TOKEN'),
        'mock' => env('POLLER_MOCK', true),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    ],

    'yandex' => [
        'client_id' => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect_uri' => env('YANDEX_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/auth/yandex/callback'),
        'scope' => env('YANDEX_SCOPE', 'login:info,login:email'),
        'authorize_url' => env('YANDEX_AUTHORIZE_URL', 'https://oauth.yandex.com/authorize'),
        'token_url' => env('YANDEX_TOKEN_URL', 'https://oauth.yandex.com/token'),
        'user_info_url' => env('YANDEX_USER_INFO_URL', 'https://login.yandex.ru/info'),
    ],

    'fake_bank' => [
        'webhook_secret' => env('FAKE_BANK_WEBHOOK_SECRET'),
    ],

];
