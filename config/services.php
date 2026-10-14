<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        // Intuit's sandbox and production APIs live at different base URLs
        // (sandbox company data isn't reachable at the production one, and
        // vice versa) - not just a query param or header switch.
        'environment' => env('QUICKBOOKS_ENVIRONMENT', 'sandbox'),
    ],

    // Feedback widget (Quackback). The URL turns the launcher on for every
    // visitor; the signing secret is server-only and signs the ssoToken that
    // identifies signed-in users - it must never reach the browser.
    'quackback' => [
        'url' => env('QUACKBACK_URL'),
        'signing_secret' => env('QUACKBACK_SIGNING_SECRET'),
    ],

];
