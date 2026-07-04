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

    // Reads portfolio positions from Scalable Capital via the official CLI
    // (`sc`, headless in the container). Each position is matched to the asset
    // carrying the same ISIN, so the sync updates that asset's current-month
    // row; uninvested cash (no ISIN) is synced as its own asset. Inert unless
    // the CLI is enabled.
    'scalable' => [
        'cash_category_id' => (int) env('SCALABLE_CASH_CATEGORY_ID', 0),
        'cash_asset_name' => env('SCALABLE_CASH_ASSET_NAME', 'Scalable Liquidità'),
        'cli' => [
            'enabled' => (bool) env('SCALABLE_CLI_ENABLED', false),
            'binary' => env('SCALABLE_CLI_BINARY', 'sc'),
            'timeout' => (int) env('SCALABLE_CLI_TIMEOUT', 30),
            'login_timeout' => (int) env('SCALABLE_CLI_LOGIN_TIMEOUT', 900),
        ],
    ],

    // AI advisor backend. Swappable by `driver`: a local model via Ollama for
    // development (no cloud cost), a cloud model (Claude) later. Inert until a
    // model is configured.
    'advisor' => [
        'driver' => env('ADVISOR_DRIVER', 'ollama'),
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),
            'model' => env('OLLAMA_MODEL', ''),
            'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
            // Keep the model resident between requests so a follow-up doesn't pay
            // the reload cost; low temperature for a factual advisor; an explicit
            // context window so the briefing + history isn't silently truncated.
            'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
            'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.3),
            'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
        ],
        'anthropic' => [
            'model' => env('ADVISOR_ANTHROPIC_MODEL', ''),
            'timeout' => (int) env('ADVISOR_ANTHROPIC_TIMEOUT', 120),
        ],
        // Regolo (EU, zero-data-retention) via its OpenAI-compatible endpoint.
        // Driven through Prism's OpenRouter provider (a /chat/completions client)
        // rather than the OpenAI one, which now targets /responses (see the
        // regolo branch in AppServiceProvider).
        'regolo' => [
            'base_url' => env('REGOLO_BASE_URL', 'https://api.regolo.ai/v1'),
            'api_key' => env('REGOLO_API_KEY', ''),
            'model' => env('REGOLO_MODEL', ''),
            'timeout' => (int) env('REGOLO_TIMEOUT', 120),
        ],
    ],

];
