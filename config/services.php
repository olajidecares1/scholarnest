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

        /*
         * Result details sent to parents/guardians (result link, token and
         * admission number). A separate approved "Utility" template, because
         * its values differ from the staff one. Its body takes eight values,
         * worded as on Settings > WhatsApp:
         *   {{1}} the parent's name      {{2}} the school's name
         *   {{3}} the term and session   {{4}} the student's name
         *   {{5}} the class              {{6}} the admission number
         *   {{7}} the result token       {{8}} the result-checking link
         * Without it, plain text is sent, which WhatsApp only delivers to a
         * parent who has messaged the school's number in the last 24 hours;
         * the delivery receipts below then mark the rest as failed.
         */
        'result_template' => env('WHATSAPP_RESULT_TEMPLATE'),

        /*
         * Verifying a school's WhatsApp number (Settings > WhatsApp): a
         * 6-digit code is sent to it from the platform's number and typed
         * back. An approved "Authentication" template with a "Copy code"
         * button; Meta writes its wording ("{{1}} is your verification code").
         */
        'verify_template' => env('WHATSAPP_VERIFY_TEMPLATE'),
        'verify_template_language' => env('WHATSAPP_VERIFY_TEMPLATE_LANGUAGE', 'en'),

        /*
         * Delivery receipts. Point the WhatsApp app's webhook at
         * https://your-domain/api/webhooks/whatsapp, with this verify token,
         * and subscribe to "messages". The app secret proves each receipt
         * came from Meta; without it receipts are refused.
         */
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],

];
