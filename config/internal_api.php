<?php

return [
    'require_signature' => (bool) env('INTERNAL_API_REQUIRE_SIGNATURE', true),
    'secret' => env('INTERNAL_API_SECRET', ''),
    'client_id' => env('INTERNAL_API_CLIENT_ID', ''),
    'allowed_ips' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('INTERNAL_API_ALLOWED_IPS', ''))
    ))),
    'max_skew_seconds' => (int) env('INTERNAL_API_MAX_SKEW_SECONDS', 300),
    'request_id_ttl_seconds' => (int) env('INTERNAL_API_REQUEST_ID_TTL_SECONDS', 300),
];
