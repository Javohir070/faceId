<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('telegram.webhook_secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
