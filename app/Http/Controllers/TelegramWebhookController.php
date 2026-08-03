<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramAuthHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramAuthHandler $handler): JsonResponse
    {
        $handler->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
