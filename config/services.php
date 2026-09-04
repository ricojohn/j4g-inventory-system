<?php

use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;

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

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'models' => [
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4.1-mini',
        ],
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
    ],

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-1.5-flash'),
        'models' => [
            'gemini-1.5-flash',
            'gemini-2.0-flash',
            'gemini-2.5-flash',
        ],
        'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 60),
    ],

    'ai' => [
        'providers' => [
            'openai' => [
                'label' => 'OpenAI',
                'class' => OpenAiProvider::class,
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'class' => GeminiProvider::class,
            ],
        ],
    ],

    'facebook' => [
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
        'graph_api_version' => env('FACEBOOK_GRAPH_API_VERSION', 'v23.0'),
        'request_timeout' => (int) env('FACEBOOK_REQUEST_TIMEOUT', 15),
    ],

];
