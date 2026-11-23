<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ApiUserLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tashqi API larga panel foydalanuvchisi sifatida kirish (docs/api-auth.md): `Authorization: Bearer <token>`
 * (POST /api/auth/token) yoki HTTP Basic email + parol; rolida `menu_permissions.api.<kalit>` ruxsati bo'lishi shart.
 * Route da `api.user:parking` yoki `api.user:integration` (docs/parking-api.md, docs/integration-api.md).
 *
 * Tekshiruv sessiyasiz: foydalanuvchi faqat shu so'rov uchun tanilar, cookie ham, sessiya ham ochilmaydi.
 * Parol tekshiruvi va xato urinish cheklovi — ApiUserLogin (token olish bilan umumiy).
 */
class AuthenticateApiUser
{
    public function __construct(private readonly ApiUserLogin $login) {}

    public function handle(Request $request, Closure $next, string $ruxsatKaliti): Response
    {
        // Tashqi tizim Accept sarlavhasini yubormasa ham xatolar (masalan 422) redirect emas, JSON bo'lsin.
        $request->headers->set('Accept', 'application/json');

        $token = (string) $request->bearerToken();

        if ($token !== '') {
            $user = $this->login->fromToken($token);

            if ($user === null) {
                Log::warning('Tashqi API: token noto‘g‘ri yoki muddati tugagan', ['path' => $request->path()]);

                return ApiUserLogin::bearerChallenge('Token noto‘g‘ri yoki muddati tugagan. Yangi token oling.');
            }
        } else {
            $email = (string) $request->getUser();
            $parol = (string) $request->getPassword();

            // Ko'p HTTP klientlar parolni faqat 401 chaqirig'idan keyin yuboradi — bu xato urinish emas,
            // shuning uchun cheklovga ham, logga ham tushmaydi.
            if ($email === '' || $parol === '') {
                return ApiUserLogin::basicChallenge('Email va parol yuborilmagan.');
            }

            $user = $this->login->attempt($request, $email, $parol);

            if (! $user instanceof User) {
                return $user;
            }
        }

        $ruxsat = (string) config("menu_permissions.api.{$ruxsatKaliti}");

        if ($ruxsat === '' || ! $user->can($ruxsat)) {
            Log::warning('Tashqi API: foydalanuvchida ruxsat yo‘q', ['user_id' => $user->id, 'path' => $request->path()]);

            return ApiUserLogin::error('FORBIDDEN', "Bu foydalanuvchiga “{$ruxsat}” ruxsati berilmagan.", 403);
        }

        return $next($request);
    }
}
