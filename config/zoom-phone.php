<?php

return [
    'smart_embed_enabled' => env('ZOOM_PHONE_SMART_EMBED_ENABLED', false),
    'smart_embed_url' => env('ZOOM_PHONE_SMART_EMBED_URL', 'https://applications.zoom.us/integration/phone/embeddablephone/home'),
    'account_id' => env('ZOOM_PHONE_ACCOUNT_ID'),
    'client_id' => env('ZOOM_PHONE_CLIENT_ID'),
    'client_secret' => env('ZOOM_PHONE_CLIENT_SECRET'),
    'oauth_url' => env('ZOOM_PHONE_OAUTH_URL', 'https://zoom.us/oauth/token'),
    'api_url' => env('ZOOM_PHONE_API_URL', 'https://api.zoom.us'),
];
