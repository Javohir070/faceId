<?php

namespace App\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * So‘rov yuborgan mijozning ishonchli IP manzili.
 *
 * bootstrap/app.php da trustProxies(at: '*') turgani uchun $request->ip() X-Forwarded-For sarlavhasidan
 * olinadi, uni esa istalgan mijoz o‘zi yozib yubora oladi (nginx so‘rovni to‘g‘ridan-to‘g‘ri php-fpm ga
 * uzatganda). Shuning uchun sarlavhaga faqat so‘rov shu serverning o‘zidagi proksidan (loopback) kelganda
 * ishonamiz, qolgan hollarda haqiqiy ulanish manzili — REMOTE_ADDR.
 *
 * IP ga qarab ruxsat beriladigan yoki qurilma aniqlanadigan joyda $request->ip() o‘rniga shuni ishlating.
 */
final class ClientIp
{
    private const LOOPBACK = ['127.0.0.0/8', '::1'];

    public static function from(Request $request): ?string
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
