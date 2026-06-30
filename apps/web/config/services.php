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

    'max' => [
        'bot_token' => env('MAX_BOT_TOKEN'),
        'bot_username' => env('MAX_BOT_USERNAME'),
        'bot_url' => env('MAX_BOT_URL'),
        'webhook_secret' => env('MAX_WEBHOOK_SECRET'),
        'webhook_url' => env('MAX_WEBHOOK_URL'),
        'api_base_url' => env('MAX_API_BASE_URL', 'https://botapi.max.ru'),
        'send_message_url' => env('MAX_SEND_MESSAGE_URL'),
        'auth_mode' => env('MAX_API_AUTH_MODE', 'query'),
        'token_query_parameter' => env('MAX_TOKEN_QUERY_PARAMETER', 'access_token'),
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

    'vk' => [
        'client_id' => env('VK_CLIENT_ID'),
        'client_secret' => env('VK_CLIENT_SECRET'),
        'redirect_uri' => env('VK_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/auth/vk/callback'),
        'scope' => env('VK_SCOPE', 'email'),
        'authorize_url' => env('VK_AUTHORIZE_URL', 'https://oauth.vk.com/authorize'),
        'token_url' => env('VK_TOKEN_URL', 'https://oauth.vk.com/access_token'),
        'user_info_url' => env('VK_USER_INFO_URL', 'https://api.vk.com/method/users.get'),
        'api_version' => env('VK_API_VERSION', '5.199'),
    ],

    'google' => [
        'enabled' => env('GOOGLE_AUTH_ENABLED', false),
    ],

    'fake_bank' => [
        'webhook_secret' => env('FAKE_BANK_WEBHOOK_SECRET'),
    ],

    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'robokassa'),
    ],

    'robokassa' => [
        'mode' => env('ROBOKASSA_MODE', 'test'),
        'merchant_login' => env('ROBOKASSA_MERCHANT_LOGIN'),
        'password1' => env('ROBOKASSA_PASSWORD1'),
        'password2' => env('ROBOKASSA_PASSWORD2'),
        'test_password1' => env('ROBOKASSA_TEST_PASSWORD1'),
        'test_password2' => env('ROBOKASSA_TEST_PASSWORD2'),
        'hash_algorithm' => env('ROBOKASSA_HASH_ALGORITHM', 'md5'),
        'payment_url' => env('ROBOKASSA_PAYMENT_URL', 'https://auth.robokassa.ru/Merchant/Index.aspx'),
        'culture' => env('ROBOKASSA_CULTURE', 'ru'),
    ],

    'yookassa' => [
        'mode' => env('YOOKASSA_MODE', 'test'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'api_url' => env('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3'),
        'webhook_secret' => env('YOOKASSA_WEBHOOK_SECRET'),
    ],

];

