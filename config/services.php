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

    // GitHub deploy webhook (same contract as the webpay modern /github/deploy hook).
    // webhook_secret: shared secret used to verify X-Hub-Signature-256.
    // deploy_token : fine-grained PAT used to fetch the repo over HTTPS.
    //                It is passed to git as an ephemeral URL token and never
    //                written to .git/config.
    // Keys stay in the environment. Empty values leave speech transcription off.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'speech_model' => env('OPENAI_SPEECH_MODEL', 'whisper-1'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // true verifies the OpenAI certificate. A CA bundle is required when PHP has no curl.cainfo.
        'verify_ssl' => filter_var(env('OPENAI_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('OPENAI_CA_BUNDLE', storage_path('app/certs/cacert.pem')),
    ],

    'huggingface' => [
        'token' => env('HUGGINGFACE_TOKEN'),
        'speech_model' => env('HUGGINGFACE_SPEECH_MODEL', 'openai/whisper-large-v3'),
        'inference_url' => env('HUGGINGFACE_INFERENCE_URL', 'https://router.huggingface.co/hf-inference/models'),
    ],

    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'deploy_token' => env('GITHUB_DEPLOY_TOKEN'),
        'deploy_owner' => env('GITHUB_DEPLOY_OWNER', 'mansouranos'),
        'deploy_repo' => env('GITHUB_DEPLOY_REPO', 'gekkoai'),
    ],

];
