<?php

$baseUri = trim((string) env('HIKVISION_BASE_URI', ''));

if ($baseUri !== '' && ! preg_match('#^https?://#i', $baseUri)) {
    $baseUri = 'http://'.$baseUri;
}

if ($baseUri !== '') {
    $baseUri = rtrim($baseUri, '/').'/';
}

return [
    'base_uri' => $baseUri,
    'username' => env('HIKVISION_USERNAME', ''),
    'password' => env('HIKVISION_PASSWORD', ''),
    'timeout' => (int) env('HIKVISION_TIMEOUT', 5),
    'webhook_secret' => env('HIKVISION_WEBHOOK_SECRET', ''),
    'webhook_trust_device_ip' => filter_var(env('HIKVISION_WEBHOOK_TRUST_DEVICE_IP', true), FILTER_VALIDATE_BOOL),
];
