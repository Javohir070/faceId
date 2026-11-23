<?php

namespace App\Http\Middleware;

use App\Http\ClientIp;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tashqi API larga panel foydalanuvchisi sifatida kirish: email va parol HTTP Basic bilan keladi, rolida
 * `menu_permissions.api.<kalit>` ruxsati bo'lishi shart. Route da `api.user:parking` yoki `api.user:integration`
 * (docs/parking-api.md, docs/integration-api.md).
 *
 * Tekshiruv sessiyasiz (`once`): foydalanuvchi faqat shu so'rov uchun tanilar, cookie ham, sessiya ham ochilmaydi.
 * Parol taxmin qilinmasligi uchun bir IP va email dan ketma-ket xatolar cheklanadi — cheklov paroldan oldin
 * tekshiriladi, aks holda to'g'ri parolni topish davom etaverardi.
 */
class AuthenticateApiUser
{
    /** Bir IP va email uchun daqiqasiga xato urinishlar. */
    private const MAX_ATTEMPTS = 5;

    /** Bir IP dan har qanday emaillar bilan daqiqasiga xato urinishlar — email almashtirib parol terishga qarshi. */
    private const MAX_IP_ATTEMPTS = 20;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next, string $ruxsatKaliti): Response
    {
        // Tashqi tizim Accept sarlavhasini yubormasa ham xatolar (masalan 422) redirect emas, JSON bo'lsin.
        $request->headers->set('Accept', 'application/json');

        $email = (string) $request->getUser();
        $parol = (string) $request->getPassword();

        // Ko'p HTTP klientlar parolni faqat 401 chaqirig'idan keyin yuboradi — bu xato urinish emas,
        // shuning uchun cheklovga ham, logga ham tushmaydi.
        if ($email === '' || $parol === '') {
            return $this->kirishKerak('Email va parol yuborilmagan.');
        }

        $ip = ClientIp::from($request);
        // Kalit panel loginidagi kabi normallashtiriladi: MySQL (utf8mb4_unicode_ci) katta-kichik harf, oxirgi
        // bo'shliq va urg'uga qaramay o'sha foydalanuvchini topadi — har yozilish o'z hisobini olmasligi kerak.
        // Kalit ikkala API uchun umumiy: bitta foydalanuvchining paroli ikkalasida bir xil.
        $emailKaliti = 'api-user|'.Str::transliterate(Str::lower(trim($email))).'|'.$ip;
        $ipKaliti = 'api-user-ip|'.$ip;

        $bloklangan = array_filter([
            $emailKaliti => RateLimiter::tooManyAttempts($emailKaliti, self::MAX_ATTEMPTS),
            $ipKaliti => RateLimiter::tooManyAttempts($ipKaliti, self::MAX_IP_ATTEMPTS),
        ]);

        if ($bloklangan !== []) {
            $soniya = max(array_map(fn (string $kalit): int => RateLimiter::availableIn($kalit), array_keys($bloklangan)));

            return $this->xato(
                'TOO_MANY_ATTEMPTS',
                "Juda ko‘p xato urinish. {$soniya} soniyadan keyin qayta urinib ko‘ring.",
                429,
                ['Retry-After' => (string) $soniya],
            );
        }

        $guard = Auth::guard('web');

        if (! $guard->once(['email' => $email, 'password' => $parol])) {
            RateLimiter::hit($emailKaliti, self::DECAY_SECONDS);
            RateLimiter::hit($ipKaliti, self::DECAY_SECONDS);

            // Parol logga yozilmaydi.
            Log::warning('Tashqi API: email yoki parol noto‘g‘ri', ['email' => $email, 'ip' => $ip, 'path' => $request->path()]);

            return $this->kirishKerak('Email yoki parol noto‘g‘ri.');
        }

        // IP hisobi ataylab tozalanmaydi: bitta haqiqiy akkaunt bilan uni nolga tushirib, boshqa emaillarga
        // parol terishni davom ettirib bo'lardi.
        RateLimiter::clear($emailKaliti);

        $user = $guard->user();

        $ruxsat = (string) config("menu_permissions.api.{$ruxsatKaliti}");

        if ($ruxsat === '' || ! $user->can($ruxsat)) {
            Log::warning('Tashqi API: foydalanuvchida ruxsat yo‘q', ['user_id' => $user->id, 'ip' => $ip, 'path' => $request->path()]);

            return $this->xato('FORBIDDEN', "Bu foydalanuvchiga “{$ruxsat}” ruxsati berilmagan.", 403);
        }

        return $next($request);
    }

    /** 401 va WWW-Authenticate: HTTP klientlar shu sarlavha bo'lsagina Basic ma'lumotini yuborishni biladi. */
    private function kirishKerak(string $xabar): JsonResponse
    {
        return $this->xato('UNAUTHORIZED', $xabar, 401, [
            'WWW-Authenticate' => 'Basic realm="Biocontrol API", charset="UTF-8"',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function xato(string $kod, string $xabar, int $status, array $headers = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], $status, $headers);
    }
}
