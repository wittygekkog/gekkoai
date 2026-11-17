<?php

return [
    /*
    | Clients that may call this application's connector endpoints.
    | Map a key id to its shared secret. Secrets belong in the environment,
    | never in the database in plaintext and never in the browser.
    */
    'clients' => array_filter([
        env('SBU_SIMULATION_KEY_ID', 'sim-inner-cove') => env('SBU_SIMULATION_SECRET'),
    ]),

    'replay_leeway_seconds' => (int) env('SBU_REPLAY_LEEWAY', 300),

    'version' => '1.0.0',
];
