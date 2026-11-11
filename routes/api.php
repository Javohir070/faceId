<?php

use App\Http\Controllers\LegacyApiController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\RestrictLegacyApiIps;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hikvision/events/{secret?}', [WebhookController::class, 'hikvision']);

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook');

// Eski tizimning api.php’si o‘rnida (docs/legacy-api.md): faqat LEGACY_API_ALLOWED_IPS dan.
Route::any('/legacy', LegacyApiController::class)
    ->middleware(RestrictLegacyApiIps::class)
    ->name('api.legacy');
