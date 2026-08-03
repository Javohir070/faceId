<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hikvision/events/{secret?}', [WebhookController::class, 'hikvision']);

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook');
