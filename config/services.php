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
     * WhatsApp Business (Meta Cloud API), for messages the system sends on its
     * own, such as login details after a bulk staff upload.
     *
     * Business-initiated messages must use a template Meta has approved, so
     * set WHATSAPP_TEMPLATE to an approved "Utility" template whose body takes
     * five values, in this order:
     *   {{1}} the person's name    {{2}} the school's name
     *   {{3}} their Staff ID       {{4}} their password
     *   {{5}} the sign-in link
     * Without a template, a plain text message is sent, which WhatsApp only
     * delivers to someone who has messaged the school's number in the last 24
     * hours. With no token or phone number ID nothing is sent at all, and the
     * School Admin is offered a WhatsApp share link for each person instead.
     */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'template' => env('WHATSAPP_TEMPLATE'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    ],

];
