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
    'web_search_provider' => env('LEAD_WEB_SEARCH_PROVIDER', 'brave'),
    'intent_tags' => [
        'booking_system' => 'Booking System',
        'ai_receptionist' => 'AI Receptionist',
        'mobile_business' => 'Mobile Business',
        'reservations' => 'Reservations',
    ],
    'web_search_limits' => [
        'quick' => 10,
        'standard' => 20,
        'deep' => 20,
        'max' => 20,
    ],
    'web_search_excluded_domains' => [
        'bing.com',
        'classpass.com',
        'facebook.com',
        'google.com',
        'instagram.com',
        'linkedin.com',
        'mapquest.com',
        'tripadvisor.com',
        'x.com',
        'yellowpages.com',
        'yelp.com',
        'youtube.com',
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
    'crawl_max_pages' => (int) env('LEAD_CRAWL_MAX_PAGES', 10),
    'website_freshness' => [
        'enabled' => env('LEAD_WEBSITE_FRESHNESS_ENABLED', true),
        'recent_days' => (int) env('LEAD_WEBSITE_FRESHNESS_RECENT_DAYS', 30),
        'recheck_days' => (int) env('LEAD_WEBSITE_FRESHNESS_RECHECK_DAYS', 30),
        'high_confidence_score' => (int) env('LEAD_WEBSITE_FRESHNESS_HIGH_SCORE', 70),
        'timeout_seconds' => (int) env('LEAD_WEBSITE_FRESHNESS_TIMEOUT', 8),
        'user_agent' => env('LEAD_WEBSITE_FRESHNESS_USER_AGENT', 'MirandaLeadResearch/1.0'),
        'rdap_url' => env('LEAD_WEBSITE_FRESHNESS_RDAP_URL', 'https://rdap.org/domain'),
        'certificate_url' => env('LEAD_WEBSITE_FRESHNESS_CERTIFICATE_URL', 'https://crt.sh/'),
        'archive_url' => env('LEAD_WEBSITE_FRESHNESS_ARCHIVE_URL', 'https://web.archive.org/cdx/search/cdx'),
    ],
    'new_websites' => [
        'enabled' => env('LEAD_NEW_WEBSITES_ENABLED', true),
        'public_sample_enabled' => env('LEAD_NEW_WEBSITES_PUBLIC_SAMPLE_ENABLED', true),
        'public_sample_url' => env(
            'LEAD_NEW_WEBSITES_PUBLIC_SAMPLE_URL',
            'https://newly-registered-domains.whoisxmlapi.com/sample/nrd.%s.lite.daily.1000.csv'
        ),
        'daily_import_limit' => (int) env('LEAD_NEW_WEBSITES_DAILY_IMPORT_LIMIT', 1000),
        'daily_qualification_limit' => (int) env('LEAD_NEW_WEBSITES_DAILY_QUALIFICATION_LIMIT', 25),
        'minimum_priority_score' => (int) env('LEAD_NEW_WEBSITES_MINIMUM_PRIORITY_SCORE', 60),
        'request_timeout_seconds' => (int) env('LEAD_NEW_WEBSITES_REQUEST_TIMEOUT', 20),
        'blocked_tlds' => [
            'asia', 'bet', 'buzz', 'cam', 'casino', 'cfd', 'click', 'icu', 'lol',
            'mom', 'one', 'rest', 'sbs', 'surf', 'top', 'vip', 'win', 'xyz',
        ],
        'industry_terms' => [
            'restaurant' => ['restaurant', 'cafe', 'coffee', 'bistro', 'grill', 'catering', 'bakery', 'tavern', 'cocktailbar'],
            'hospitality' => ['hotel', 'motel', 'lodge', 'resort', 'bnb', 'campground'],
            'tourism' => ['tour', 'travel', 'cruise', 'adventure', 'excursion'],
            'events' => ['wedding', 'venue', 'events', 'party', 'photography', 'photographer'],
            'beauty_wellness' => ['salon', 'barber', 'spa', 'massage', 'nails', 'lashes', 'tattoo', 'pilates', 'yoga', 'fitness', 'gym'],
            'healthcare' => ['dental', 'dentist', 'orthodont', 'physio', 'therapy', 'chiro', 'podiatr', 'optom', 'audiolog', 'clinic'],
            'pet_care' => ['veterinary', 'vets', 'petcare', 'groom', 'kennel', 'dogwalk', 'dogwalker'],
            'home_services' => ['hvac', 'heating', 'cooling', 'plumb', 'roof', 'locksmith', 'restoration', 'septic', 'drain', 'sewer', 'pest', 'appliance', 'treecare', 'junkremoval', 'movers', 'moving'],
            'automotive' => ['automotive', 'autorepair', 'mechanic', 'collision', 'windscreen', 'windshield', 'tyre', 'tire', 'detailing', 'towing', 'garage'],
            'professional' => ['lawfirm', 'lawyer', 'attorney', 'legal', 'accounting', 'accountant', 'tax', 'insurance', 'mortgage', 'recruitment', 'staffing', 'realty', 'realtor', 'propertymanagement'],
            'education_family' => ['childcare', 'daycare', 'tutoring', 'musicschool', 'drivingschool', 'swimschool'],
        ],
    ],
    'email_domain_filter' => [
        'enabled' => env('LEAD_EMAIL_DOMAIN_FILTER_ENABLED', true),
        'allow_external_domains' => array_values(array_unique(array_merge(
            ['gmail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com', 'zoho.com'],
            array_filter(array_map(
                static fn ($domain) => strtolower(trim((string) $domain)),
                explode(',', (string) env('LEAD_EMAIL_ALLOW_EXTERNAL_DOMAINS', ''))
            ))
        ))),
        'deny_domains' => array_values(array_filter(array_map(
            static fn ($domain) => strtolower(trim((string) $domain)),
            explode(',', (string) env('LEAD_EMAIL_DENY_DOMAINS', 'sentry.io,sentry.wixpress.com'))
        ))),
        'deny_local_parts' => ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'example', 'test'],
    ],
];
