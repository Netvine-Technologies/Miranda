<?php

return [
    // any_change | price_change | price_drop | stock_change | back_in_stock
    'alert_mode' => env('PRICE_WATCH_ALERT_MODE', 'any_change'),
    'cooldown_minutes' => (int) env('PRICE_WATCH_COOLDOWN_MINUTES', 0),
];

