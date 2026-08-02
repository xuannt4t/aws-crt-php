<?php

return [
    'admin_email' => env('DORMIDA_ADMIN_EMAIL', 'admin@dormida.test'),
    'admin_password' => env('DORMIDA_ADMIN_PASSWORD', 'password'),

    'attachments' => [
        'disk' => env('DORMIDA_ATTACHMENT_DISK', 'local'),
    ],
];
