<?php

declare(strict_types=1);

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'enable_banking' => [
        'application_id' => env('ENABLE_BANKING_APPLICATION_ID', ''),
        'private_key_path' => env('ENABLE_BANKING_PRIVATE_KEY_PATH', ''),
        'redirect_url' => env('ENABLE_BANKING_REDIRECT_URL', ''),
    ],

    // Reads portfolio positions from Scalable Capital. Two sources: the official
    // CLI (`sc`, headless in the container) and the unofficial proxy on the host
    // (stopgap). `source` picks which — 'auto' prefers a logged-in CLI and falls
    // back to the proxy. Each position is matched to the asset carrying the same
    // ISIN, so the sync updates that asset's current-month row; uninvested cash
    // (no ISIN) is synced as its own asset. Inert unless a source is configured.
    'scalable' => [
        'balance_url' => env('SCALABLE_BALANCE_URL', ''),
        'token' => env('SCALABLE_GATEWAY_TOKEN', ''),
        'cash_category_id' => (int) env('SCALABLE_CASH_CATEGORY_ID', 0),
        'cash_asset_name' => env('SCALABLE_CASH_ASSET_NAME', 'Scalable Liquidità'),
        'source' => env('SCALABLE_SOURCE', 'auto'),
        'cli' => [
            'enabled' => (bool) env('SCALABLE_CLI_ENABLED', false),
            'binary' => env('SCALABLE_CLI_BINARY', 'sc'),
            'timeout' => (int) env('SCALABLE_CLI_TIMEOUT', 30),
        ],
    ],

];
