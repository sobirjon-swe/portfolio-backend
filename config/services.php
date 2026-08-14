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

    /*
     * Private Telegram chat that receives "new enquiry / new comment" alerts.
     * Both values must be set for anything to be sent; with either missing the
     * notifier stays quiet instead of erroring.
     *
     * bot_token: from @BotFather.
     * chat_id:   your own numeric id — message the bot once, then read
     *            https://api.telegram.org/bot<TOKEN>/getUpdates
     */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'leetcode' => [
        // `?:` rather than an env() default: a present-but-empty LEETCODE_USERNAME
        // resolves to '' and would silently disable the stats endpoint.
        'username' => env('LEETCODE_USERNAME') ?: 'Sobirjon-swe',
    ],

];
