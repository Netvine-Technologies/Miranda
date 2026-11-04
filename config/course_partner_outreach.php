<?php

return [
    'enabled' => env('COURSE_PARTNER_OUTREACH_ENABLED', false),
    'mailer' => env('COURSE_PARTNER_MAILER', 'course_partner_smtp'),
    'tracking_base_url' => env('COURSE_PARTNER_TRACKING_BASE_URL'),
    'sender_address' => env('COURSE_PARTNER_FROM_ADDRESS', ''),
    'sender_name' => env('COURSE_PARTNER_FROM_NAME', 'Shane at Booking Tree'),
    'reply_to' => env('COURSE_PARTNER_REPLY_TO', 'support@bookingtree.io'),
    'review_address' => env('COURSE_PARTNER_REVIEW_EMAIL', 'shane.johnson386@gmail.com'),
    'commission_percent' => (int) env('COURSE_PARTNER_COMMISSION_PERCENT', 20),
    'commission_months' => (int) env('COURSE_PARTNER_COMMISSION_MONTHS', 24),
    'campaign_key' => 'educator-partnership-v1',
    'logo_url' => 'https://bookingtree.io/booking-tree-logo-white.png',
    'partner_enquiry_url' => 'https://bookingtree.io/partners/educators',
];
