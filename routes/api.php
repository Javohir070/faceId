<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WebhookController;

Route::post('/webhooks/hikvision/events/{secret?}', [WebhookController::class, 'hikvision']);