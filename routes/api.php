<?php

use App\Http\Controllers\Api\V1\DirectoryController;
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

/*
|--------------------------------------------------------------------------
| Ma’lumotnoma — tashqi tizimlar uchun (v1)
|--------------------------------------------------------------------------
| Bo‘limlar, guruhlar va xodimlar. Faqat o‘qiydi; himoyasi HTTP Basic
| (DIRECTORY_API_USER / DIRECTORY_API_PASSWORD). Batafsil: docs/directory-api.md.
*/
Route::middleware('directory.api')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/departments', [DirectoryController::class, 'departments'])->name('departments');
    Route::get('/groups', [DirectoryController::class, 'groups'])->name('groups');
    Route::get('/employees', [DirectoryController::class, 'employees'])->name('employees');
});
