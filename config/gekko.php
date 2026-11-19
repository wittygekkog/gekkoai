<?php

return [
    'timezone' => env('GEKKO_DEFAULT_TIMEZONE', 'Asia/Manila'),

    // Writes stay in simulation until a connector mode is switched to live on purpose.
    'allow_live_connectors' => (bool) env('GEKKO_ALLOW_LIVE_CONNECTORS', false),

    // Test hook. Production must leave this false.
    'simulate_timeout_after_commit' => false,

    /*
    | Speech transcription for workspace and mobile voice.
    | none — store the recording and ask for a typed transcript.
    | openai — OpenAI audio transcriptions (needs OPENAI_API_KEY).
    | huggingface — Hugging Face inference speech model (needs HUGGINGFACE_TOKEN).
    */
    'speech_provider' => env('SPEECH_PROVIDER', 'none'),

    'audio_max_kilobytes' => (int) env('AUDIO_MAX_KB', 8192),
    'audio_max_seconds' => (int) env('AUDIO_MAX_SECONDS', 60),
    'audio_retention_days' => (int) env('AUDIO_RETENTION_DAYS', 30),

    'uncalibrated_confidence_cap' => 0.86,
    'minimum_calibration_samples' => 30,
    'read_confidence_floor' => 0.80,
];
