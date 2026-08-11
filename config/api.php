<?php

return [
    'tokens' => [
        'access_ttl_minutes' => (int) env('API_ACCESS_TOKEN_TTL_MINUTES', 300),
        'refresh_ttl_days' => (int) env('API_REFRESH_TOKEN_TTL_DAYS', 30),
    ],
];
