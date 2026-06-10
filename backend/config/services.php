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

    'live_score' => [
        'base_url' => env('LIVE_SCORE_API_BASE_URL', 'https://livescore-api.com/api-client'),
        'key' => env('LIVE_SCORE_API_KEY'),
        'secret' => env('LIVE_SCORE_API_SECRET'),
        'default_lang' => env('LIVE_SCORE_API_LANG', 'en'),
        'competition_id' => env('LIVE_SCORE_API_COMPETITION_ID'),
        'competition_ids' => env('LIVE_SCORE_API_COMPETITION_IDS'),
        'season' => env('LIVE_SCORE_API_SEASON'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'tokeninfo_url' => env('GOOGLE_TOKENINFO_URL', 'https://oauth2.googleapis.com/tokeninfo'),
    ],

    'push' => [
        'vapid_public_key' => env('PUSH_VAPID_PUBLIC_KEY'),
        'vapid_private_key' => env('PUSH_VAPID_PRIVATE_KEY'),
        'vapid_subject' => env('PUSH_VAPID_SUBJECT', 'mailto:soporte@localhost'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],

];
