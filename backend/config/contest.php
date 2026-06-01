<?php

return [
    'name' => env('CONTEST_NAME', 'Polla Mundialista Super Carnes 2026'),
    'registration_deadline' => env('CONTEST_REGISTRATION_DEADLINE', '2026-06-10 23:59:59'),
    'winner_announcement_date' => env('CONTEST_WINNER_ANNOUNCEMENT_DATE', '2026-07-10'),
    'winner_slots' => (int) env('CONTEST_WINNER_SLOTS', 3),
    'minimum_invoice_amount' => (float) env('CONTEST_MINIMUM_INVOICE_AMOUNT', 25),
    'max_invoice_age_days' => (int) env('CONTEST_MAX_INVOICE_AGE_DAYS', 1),
    'allow_google_auth' => filter_var(env('CONTEST_ALLOW_GOOGLE_AUTH', false), FILTER_VALIDATE_BOOL),
    'dgi_verifier_url' => env('DGI_VERIFIER_URL'),
    'dgi_verifier_token' => env('DGI_VERIFIER_TOKEN'),
    'employee_identity_denylist' => env('CONTEST_EMPLOYEE_IDENTITY_DENYLIST', ''),
    'official_issuer_rucs' => array_filter(array_map('trim', explode(',', (string) env('CONTEST_OFFICIAL_ISSUER_RUCS', '')))),
    'recaptcha_secret' => env('RECAPTCHA_SECRET_KEY'),
    'recaptcha_site_key' => env('VITE_RECAPTCHA_SITE_KEY'),
    'block_non_panama_ip' => filter_var(env('CONTEST_BLOCK_NON_PANAMA_IP', false), FILTER_VALIDATE_BOOL),
];
