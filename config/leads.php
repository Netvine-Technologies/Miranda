<?php

return [
    'google_places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    'google_places_endpoint' => env('GOOGLE_PLACES_ENDPOINT', 'https://places.googleapis.com/v1'),
    'google_places_region_code' => env('GOOGLE_PLACES_REGION_CODE', 'GB'),
    'scan_depth_default' => env('LEAD_SCAN_DEPTH_DEFAULT', 'standard'),
    'scan_depth_modes' => [
        'quick' => ['max_pages' => 1, 'max_results' => 20],
        'standard' => ['max_pages' => 3, 'max_results' => 60],
        'deep' => ['max_pages' => 5, 'max_results' => 100],
        'max' => ['max_pages' => 10, 'max_results' => 200],
    ],
    'pricing' => [
        'text_search_pro_per_1000' => (float) env('GOOGLE_PLACES_TEXT_SEARCH_PRO_PER_1000', 32.0),
        'place_details_pro_per_1000' => (float) env('GOOGLE_PLACES_PLACE_DETAILS_PRO_PER_1000', 17.0),
        'free_calls_per_sku_per_month' => (int) env('GOOGLE_PLACES_FREE_CALLS_PER_SKU_PER_MONTH', 5000),
    ],
    'crawl_paths' => [
        '/',
        '/contact',
        '/contact-us',
        '/about',
        '/about-us',
    ],
    'email_domain_filter' => [
        'enabled' => env('LEAD_EMAIL_DOMAIN_FILTER_ENABLED', true),
        'allow_external_domains' => array_values(array_filter(array_map(
            static fn ($domain) => strtolower(trim((string) $domain)),
            explode(',', (string) env('LEAD_EMAIL_ALLOW_EXTERNAL_DOMAINS', ''))
        ))),
        'deny_domains' => array_values(array_filter(array_map(
            static fn ($domain) => strtolower(trim((string) $domain)),
            explode(',', (string) env('LEAD_EMAIL_DENY_DOMAINS', 'sentry.io,sentry.wixpress.com'))
        ))),
    ],
];
