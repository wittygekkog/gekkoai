<?php

return [
    /*
    | Analyzer used for instructions.
    | deterministic — bounded parser, no network, used for tests and as the safety baseline.
    | http — the Python AI service. If it is unreachable, the platform falls back to the
    |        deterministic parser and records that fallback. Eligibility is always recalculated.
    */
    'analyzer' => env('GEKKO_ANALYZER', 'deterministic'),
    'ai_service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8090'),
    'ai_service_token' => env('AI_SERVICE_TOKEN'),
    'ai_service_timeout' => (int) env('AI_SERVICE_TIMEOUT', 8),

    'timezone' => env('GEKKO_DEFAULT_TIMEZONE', 'Asia/Manila'),

    // Writes stay in simulation until a connector mode is switched to live on purpose.
    'allow_live_connectors' => (bool) env('GEKKO_ALLOW_LIVE_CONNECTORS', false),

    // Test hook. Production must leave this false.
    'simulate_timeout_after_commit' => false,

    'audio_max_kilobytes' => (int) env('AUDIO_MAX_KB', 8192),
    'audio_max_seconds' => (int) env('AUDIO_MAX_SECONDS', 60),
    'audio_retention_days' => (int) env('AUDIO_RETENTION_DAYS', 30),

    'uncalibrated_confidence_cap' => 0.86,
    'minimum_calibration_samples' => 30,
    'read_confidence_floor' => 0.80,
];
