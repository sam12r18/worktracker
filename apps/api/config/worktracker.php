<?php

return [
    /*
    | Public-host security defaults are intentionally fail-closed.
    | Set WORKTRACKER_ADMIN_EMAILS to one or more authenticated account emails.
    */
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('WORKTRACKER_ADMIN_EMAILS', ''))
    ))),

    'allow_any_authenticated_user' => (bool) env('WORKTRACKER_ALLOW_ANY_AUTHENTICATED_USER', false),
    'require_https' => (bool) env('WORKTRACKER_REQUIRE_HTTPS', true),

    'device_token_expiration_days' => (int) env('WORKTRACKER_DEVICE_TOKEN_DAYS', 90),
    'admin_token_expiration_days' => (int) env('WORKTRACKER_ADMIN_TOKEN_DAYS', 30),

    // Keep storage/API timestamps in UTC; convert to this timezone at the UI/report boundary.
    'display_timezone' => (string) env('WORKTRACKER_DISPLAY_TIMEZONE', 'Asia/Tehran'),

    // Activity Intelligence defaults must stay in parity with the Windows Agent.
    'activity_intelligence' => [
        'projection_version' => 'alpha.7.3-p1',
        'merge_gap_seconds' => 15,
        'initial_anchor_seconds' => 60,
        'bridge_max_seconds' => 120,
        'bridge_rearm_seconds' => 120,
        // Sync rebuild is best-effort and intentionally capped so a historical backlog cannot stall device sync.
        'sync_rebuild_max_dates' => 7,
    ],
];
