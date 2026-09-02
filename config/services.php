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

    'smslenz' => [
        'enabled' => env('SMSLENZ_ENABLED', false),
        'base_url' => env('SMSLENZ_BASE_URL', 'https://smslenz.lk/api'),
        'user_id' => env('SMSLENZ_USER_ID'),
        'api_key' => env('SMSLENZ_API_KEY'),
        'sender_id' => env('SMSLENZ_SENDER_ID', 'SMSlenzDEMO'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'google/gemma-4-26b-a4b-it:free'),
        'fallbacks' => array_filter(array_map('trim', explode(',', (string) env('OPENROUTER_FALLBACK_MODELS', 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free,z-ai/glm-5.2:free')))),
        'referer' => env('OPENROUTER_REFERER', env('APP_URL', 'https://randalu-pc.lk')),
        'title' => env('OPENROUTER_TITLE', env('APP_NAME', 'Randalu PC')),
        'enabled' => env('OPENROUTER_ENABLED', true),
        'max_tokens' => env('OPENROUTER_MAX_TOKENS', 250),
    ],

];
