<?php

namespace App\Http\Middleware;

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
    private const LOOPBACK = ['127.0.0.0/8', '::1'];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->clientIp($request);
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

    /**
     * bootstrap/app.php da trustProxies(at: '*') turgani uchun $request->ip() X-Forwarded-For sarlavhasidan
     * olinadi, uni esa istalgan mijoz o‘zi yozib yubora oladi. Shuning uchun sarlavhaga faqat so‘rov
     * shu serverning o‘zidagi proksidan (loopback) kelganda ishonamiz, qolgan hollarda haqiqiy ulanish manzili.
     */
    private function clientIp(Request $request): ?string
    {
        $remote = (string) $request->server('REMOTE_ADDR', '');

        if ($remote === '') {
            return null;
        }

        if (IpUtils::checkIp($remote, self::LOOPBACK)) {
            return $request->ip();
        }

        return $remote;
    }
}
