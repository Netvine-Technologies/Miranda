<?php

return [
    'default_provider' => env('SEARCH_DISCOVERY_PROVIDER', 'null'),

    'default_limit' => (int) env('SEARCH_DISCOVERY_DEFAULT_LIMIT', 50),

    'default_phrases' => [
        'DM to book',
        'book via DM',
        'message to book',
        'DM for appointments',
        'taking bookings',
        'bookings open',
        'availability this week',
        'limited appointments',
        'deposit required',
        'cancellation policy',
    ],

    'default_niches' => [
        'nails',
        'nail tech',
        'lashes',
        'lash tech',
        'brows',
        'barber',
        'hair stylist',
        'beauty',
        'makeup artist',
        'massage',
        'personal trainer',
        'dog groomer',
        'tattoo',
    ],

    'appointment_terms' => [
        'appointments',
        'appointment',
        'bookings',
        'booking',
        'availability',
        'slots',
        'deposit',
        'cancellation policy',
        'taking bookings',
        'limited appointments',
    ],

    'strong_phrase_terms' => [
        'dm to book',
        'book via dm',
        'message to book',
        'dm for appointments',
    ],

    'irrelevant_terms' => [
        'job',
        'jobs',
        'hiring',
        'vacancy',
        'amazon',
        'etsy',
        'youtube',
        'linkedin',
    ],

    'ignored_instagram_paths' => [
        'p',
        'reel',
        'reels',
        'stories',
        'explore',
        'accounts',
        'direct',
        'tv',
    ],

    'reserved_instagram_handles' => [
        'about',
        'accounts',
        'developer',
        'direct',
        'explore',
        'p',
        'reel',
        'reels',
        'stories',
        'tv',
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
        ],
        'serpapi' => [
            'driver' => 'serpapi',
            'base_url' => env('SEARCH_DISCOVERY_SERPAPI_BASE_URL', 'https://serpapi.com/search.json'),
            'api_key' => env('SEARCH_DISCOVERY_SERPAPI_API_KEY'),
            'engine' => env('SEARCH_DISCOVERY_SERPAPI_ENGINE', 'google'),
            'google_domain' => env('SEARCH_DISCOVERY_SERPAPI_GOOGLE_DOMAIN', 'google.co.uk'),
            'gl' => env('SEARCH_DISCOVERY_SERPAPI_GL', 'uk'),
            'hl' => env('SEARCH_DISCOVERY_SERPAPI_HL', 'en'),
            'location' => env('SEARCH_DISCOVERY_SERPAPI_LOCATION'),
            'safe' => env('SEARCH_DISCOVERY_SERPAPI_SAFE', 'off'),
            'timeout' => (int) env('SEARCH_DISCOVERY_SERPAPI_TIMEOUT', 20),
        ],
        'http' => [
            'driver' => 'http',
            'base_url' => env('SEARCH_DISCOVERY_HTTP_BASE_URL'),
            'results_path' => env('SEARCH_DISCOVERY_HTTP_RESULTS_PATH', 'results'),
            'query_param' => env('SEARCH_DISCOVERY_HTTP_QUERY_PARAM', 'q'),
            'limit_param' => env('SEARCH_DISCOVERY_HTTP_LIMIT_PARAM', 'num'),
            'method' => env('SEARCH_DISCOVERY_HTTP_METHOD', 'GET'),
            'timeout' => (int) env('SEARCH_DISCOVERY_HTTP_TIMEOUT', 15),
            'headers' => [
                'Accept' => 'application/json',
                'X-Api-Key' => env('SEARCH_DISCOVERY_HTTP_API_KEY'),
            ],
            'mappings' => [
                'title' => env('SEARCH_DISCOVERY_HTTP_MAP_TITLE', 'title'),
                'url' => env('SEARCH_DISCOVERY_HTTP_MAP_URL', 'url'),
                'snippet' => env('SEARCH_DISCOVERY_HTTP_MAP_SNIPPET', 'snippet'),
                'position' => env('SEARCH_DISCOVERY_HTTP_MAP_POSITION', 'position'),
            ],
        ],
    ],
];
