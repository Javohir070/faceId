<?php

return [
    'token' => env('TELEGRAM_BOT_TOKEN', ''),
    'username' => env('TELEGRAM_BOT_USERNAME', ''),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'employee_expiry_warn_days' => max((int) env('TELEGRAM_EMPLOYEE_EXPIRY_WARN_DAYS', 3), 1),
];
