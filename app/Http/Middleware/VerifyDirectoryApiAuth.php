<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ma'lumotnoma API ning HTTP Basic autentifikatsiyasi (docs/directory-api.md).
 *
 * RestrictLegacyApiIps dan farqi: bu yerda IP emas, login va parol tekshiriladi.
 * Eski API o'zgarmasligi kerak edi, bu esa yangi kanal — tashqi tizim NAT yoki
 * bulut proksisi ortidan kelsa ham ishlaydi.
 *
 * Sozlanmagan bo'lsa so'rov rad etiladi: ro'yxatda JSHSHIR bor, ya'ni ochiq
 * qolgan API shaxsiy ma'lumotni tarqatadi.
 */
class VerifyDirectoryApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $login = (string) config('directory_api.user');
        $parol = (string) config('directory_api.password');

        if ($login === '' || $parol === '') {
            Log::critical("Ma'lumotnoma API: Basic auth sozlanmagan, so'rov rad etildi", [
                'path' => $request->path(),
            ]);

            return $this->rad('API sozlanmagan. Administratorga murojaat qiling.');
        }

        $berilganLogin = (string) $request->getUser();
        $berilganParol = (string) $request->getPassword();

        // Ikkala taqqoslash ham bajariladi: `&&` qisqa tutashuvi javob vaqti
        // orqali loginning to'g'riligini bildirib qo'yardi.
        $loginMos = hash_equals($login, $berilganLogin);
        $parolMos = hash_equals($parol, $berilganParol);

        if (! $loginMos || ! $parolMos) {
            Log::warning("Ma'lumotnoma API: noto'g'ri login yoki parol", [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->rad("Login yoki parol noto'g'ri.");
        }

        return $next($request);
    }

    /**
     * 401 va WWW-Authenticate: HTTP klientlar shu sarlavha bo'lsagina Basic
     * ma'lumotini yuborishni biladi.
     */
    private function rad(string $xabar): Response
    {
        return response()->json(
            ['ok' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => $xabar]],
            401,
            ['WWW-Authenticate' => 'Basic realm="Biocontrol API", charset="UTF-8"'],
        );
    }
}
