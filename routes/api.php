<?php

use App\Http\Controllers\InnoweekWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hikvision/events/{secret?}', [WebhookController::class, 'hikvision']);

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook');

// Innoweek: karta berilishi bilan ishtirokchi darhol keladi (Bearer INNOWEEK_API_TOKEN). Zaxira — innoweek:sync.
Route::post('/webhooks/innoweek/participants', InnoweekWebhookController::class)
    ->middleware('throttle:240,1');
