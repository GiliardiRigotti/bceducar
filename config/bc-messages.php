<?php

return [
    'enabled' => env('BC_GUARDIAN_MESSAGES_ENABLED', false),
    // Explicit cutoff prevents enabling channels from replaying the historical notification backlog.
    'start_at' => env('BC_GUARDIAN_MESSAGES_START_AT'),
    'country_code' => env('BC_GUARDIAN_MESSAGES_COUNTRY_CODE', '55'),
    'channels' => [
        'sms' => ['enabled' => env('BC_SMS_ENABLED', false), 'url' => env('BC_SMS_WEBHOOK_URL'), 'token' => env('BC_SMS_WEBHOOK_TOKEN')],
        'whatsapp' => ['enabled' => env('BC_WHATSAPP_ENABLED', false), 'url' => env('BC_WHATSAPP_WEBHOOK_URL'), 'token' => env('BC_WHATSAPP_WEBHOOK_TOKEN')],
    ],
];
