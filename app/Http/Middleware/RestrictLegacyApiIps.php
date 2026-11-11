<?php

namespace App\Http\Middleware;

use App\Http\ClientIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eski API’ga faqat legacy_api.allowed_ips dagi manzillardan kirish mumkin.
 */
class RestrictLegacyApiIps
{
    public function handle(Request $request, Closure $next): Response
    {
        // $request->ip() emas: X-Forwarded-For faqat shu serverdagi proksidan kelganda hisobga olinadi.
        $ip = ClientIp::from($request);
        $allowed = (array) config('legacy_api.allowed_ips', []);

        if ($ip === null || $allowed === [] || ! IpUtils::checkIp($ip, $allowed)) {
            Log::warning('Legacy API: ruxsat etilmagan manzildan so‘rov', ['ip' => $ip]);

            return response()->json(
                ['status' => false, 'alerts' => ['Access denied']],
                Response::HTTP_FORBIDDEN,
                ['Access-Control-Allow-Origin' => '*'],
            );
        }

        return $next($request);
    }
}
