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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect' => env('GITHUB_CALLBACK_URL', ''),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', '669654626553-ina4a3hp5nng9nm8hu1e1jhh6tngko6e.apps.googleusercontent.com'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', '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'),
        'redirect' => env('GOOGLE_CALLBACK_URL', 'https://tutor.ogoul.com/social_auth/google/callback'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID', ''),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
        'redirect' => env('FACEBOOK_CALLBACK_URL', ''),
    ],
    'firebase' => [
        'credentials' => [
            'file' => env('FIREBASE_CREDENTIALS'),
        ],
        'database_uri' => env('FIREBASE_DATABASE_URI', ''),
    ],
];
