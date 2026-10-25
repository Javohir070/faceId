<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/screen/{token}/…` — zal ekranlari uchun login'siz havola (LIVE_SCREEN_TOKEN).
 * Token noto‘g‘ri yoki sozlanmagan bo‘lsa — 404 (havola borligi ham bilinmasin).
 */
class VerifyLiveScreenToken
{
    public const ATTRIBUTE = 'live_screen_token';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('live.screen_token');
        $given = (string) $request->route('token');

        abort_if($expected === '' || ! hash_equals($expected, $given), 404);

        // Controller metodlari parametrlarni tartib bo‘yicha oladi — token ularga aralashmasin.
        $request->route()->forgetParameter('token');
        $request->attributes->set(self::ATTRIBUTE, $given);

        return $next($request);
    }
}
