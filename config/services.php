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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    // Read through config() so the values survive `php artisan optimize` (config:cache).
    'coinpayments' => [
        'api_url' => env('COINPAYMENTS_API_URL'),
        'client_id' => env('COINPAYMENTS_CLIENT_ID'),
        'client_secret' => env('COINPAYMENTS_CLIENT_SECRET'),
        'ipn_secret' => env('COINPAYMENTS_IPN_SECRET'),
    ],

    'sms' => [
        // zenziva | fonnte | twilio | generic | log (empty: generic if SMS_GATEWAY_URL is set, else log)
        'driver' => env('SMS_DRIVER'),
        'gateway_url' => env('SMS_GATEWAY_URL'),
        // generic: bearer token. fonnte: device token.
        'token' => env('SMS_GATEWAY_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID'),
        'zenziva' => [
            'url' => env('ZENZIVA_URL', 'https://console.zenziva.net/reguler/api/sendsms/'),
            'userkey' => env('ZENZIVA_USERKEY'),
            'passkey' => env('ZENZIVA_PASSKEY'),
        ],
        'fonnte' => [
            'url' => env('FONNTE_URL', 'https://api.fonnte.com/send'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

];
