<?php

namespace App\Services;

use App\Http\ClientIp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tashqi API larga panel foydalanuvchisi email + paroli bilan kirish (docs/api-auth.md). Basic so'rovlar
 * (`AuthenticateApiUser`) ham, token olish (`POST /api/auth/token`) ham shu yerdan o'tadi — xato urinish hisobi
 * umumiy, aks holda biri orqali parol terish ikkinchisining cheklovini chetlab o'tardi.
 *
 * Tekshiruv sessiyasiz (`once`). Cheklov paroldan oldin tekshiriladi, aks holda to'g'ri parolni topish davom etaverardi.
 */
class ApiUserLogin
{
    /** Bir IP va email uchun daqiqasiga xato urinishlar. */
    private const MAX_ATTEMPTS = 5;

    /** Bir IP dan har qanday emaillar bilan daqiqasiga xato urinishlar — email almashtirib parol terishga qarshi. */
    private const MAX_IP_ATTEMPTS = 20;

    private const DECAY_SECONDS = 60;

    /**
     * Muvaffaqiyatda foydalanuvchi (so'rov uchun guard’ga qo'yilgan), aks holda tayyor 401 yoki 429 javob.
     */
    public function attempt(Request $request, string $email, string $password): User|JsonResponse
    {
        $ip = ClientIp::from($request);
        // Kalit panel loginidagi kabi normallashtiriladi: MySQL (utf8mb4_unicode_ci) katta-kichik harf, oxirgi
        // bo'shliq va urg'uga qaramay o'sha foydalanuvchini topadi — har yozilish o'z hisobini olmasligi kerak.
        $emailKaliti = 'api-user|'.Str::transliterate(Str::lower(trim($email))).'|'.$ip;
        $ipKaliti = 'api-user-ip|'.$ip;

        $bloklangan = array_filter([
            $emailKaliti => RateLimiter::tooManyAttempts($emailKaliti, self::MAX_ATTEMPTS),
            $ipKaliti => RateLimiter::tooManyAttempts($ipKaliti, self::MAX_IP_ATTEMPTS),
        ]);

        if ($bloklangan !== []) {
            $soniya = max(array_map(fn (string $kalit): int => RateLimiter::availableIn($kalit), array_keys($bloklangan)));

            return self::error(
                'TOO_MANY_ATTEMPTS',
                "Juda ko‘p xato urinish. {$soniya} soniyadan keyin qayta urinib ko‘ring.",
                429,
                ['Retry-After' => (string) $soniya],
            );
        }

        $guard = Auth::guard('web');

        if (! $guard->once(['email' => $email, 'password' => $password])) {
            RateLimiter::hit($emailKaliti, self::DECAY_SECONDS);
            RateLimiter::hit($ipKaliti, self::DECAY_SECONDS);

            // Parol logga yozilmaydi.
            Log::warning('Tashqi API: email yoki parol noto‘g‘ri', ['email' => $email, 'ip' => $ip, 'path' => $request->path()]);

            return self::basicChallenge('Email yoki parol noto‘g‘ri.');
        }

        // IP hisobi ataylab tozalanmaydi: bitta haqiqiy akkaunt bilan uni nolga tushirib, boshqa emaillarga
        // parol terishni davom ettirib bo'lardi.
        RateLimiter::clear($emailKaliti);

        /** @var User */
        return $guard->user();
    }

    /**
     * `Authorization: Bearer` dagi Sanctum tokeni egasi — token bor, muddati o'tmagan va egasi mavjud bo'lsa.
     * Foydalanuvchi so'rov uchun guard’ga qo'yiladi (sessiyasiz, global scope’lar Basic’dagidek ishlaydi).
     * Cheklov yo'q: token 40 belgilik tasodifiy, uni taxmin qilib bo'lmaydi.
     */
    public function fromToken(string $plainToken): ?User
    {
        $token = PersonalAccessToken::findToken($plainToken);

        if ($token === null || ($token->expires_at !== null && $token->expires_at->lte(now()))) {
            return null;
        }

        $user = $token->tokenable;

        if (! $user instanceof User) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();
        Auth::guard('web')->setUser($user->withAccessToken($token));

        return $user;
    }

    public static function bearerChallenge(string $xabar): JsonResponse
    {
        return self::error('UNAUTHORIZED', $xabar, 401, ['WWW-Authenticate' => 'Bearer realm="Biocontrol API"']);
    }

    /** 401 va WWW-Authenticate: HTTP klientlar shu sarlavha bo'lsagina Basic ma'lumotini yuborishni biladi. */
    public static function basicChallenge(string $xabar): JsonResponse
    {
        return self::error('UNAUTHORIZED', $xabar, 401, [
            'WWW-Authenticate' => 'Basic realm="Biocontrol API", charset="UTF-8"',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function error(string $kod, string $xabar, int $status, array $headers = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], $status, $headers);
    }
}
