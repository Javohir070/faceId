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
 * Parking API ga kirish (docs/parking-api.md): panel foydalanuvchisining email va paroli HTTP Basic bilan
 * keladi, rolida `Parking API` ruxsati bo'lishi shart.
 *
 * Tekshiruv sessiyasiz (`once`): foydalanuvchi faqat shu so'rov uchun tanilar, cookie ham, sessiya ham ochilmaydi.
 * Parol taxmin qilinmasligi uchun bir IP va email dan ketma-ket xatolar cheklanadi — cheklov paroldan oldin
 * tekshiriladi, aks holda to'g'ri parolni topish davom etaverardi.
 */
class AuthenticateParkingApi
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        // Parking tizimi Accept sarlavhasini yubormasa ham xatolar (masalan 422) redirect emas, JSON bo'lsin.
        $request->headers->set('Accept', 'application/json');

        $email = (string) $request->getUser();
        $parol = (string) $request->getPassword();
        $ip = ClientIp::from($request);
        $kalit = 'parking-api|'.Str::lower($email).'|'.$ip;

        if (RateLimiter::tooManyAttempts($kalit, self::MAX_ATTEMPTS)) {
            $soniya = RateLimiter::availableIn($kalit);

            return $this->xato(
                'TOO_MANY_ATTEMPTS',
                "Juda ko‘p xato urinish. {$soniya} soniyadan keyin qayta urinib ko‘ring.",
                429,
                ['Retry-After' => (string) $soniya],
            );
        }

        $guard = Auth::guard('web');

        if ($email === '' || $parol === '' || ! $guard->once(['email' => $email, 'password' => $parol])) {
            RateLimiter::hit($kalit, self::DECAY_SECONDS);

            // Parol logga yozilmaydi.
            Log::warning('Parking API: email yoki parol noto‘g‘ri', ['email' => $email, 'ip' => $ip]);

            return $this->xato(
                'UNAUTHORIZED',
                'Email yoki parol noto‘g‘ri.',
                401,
                ['WWW-Authenticate' => 'Basic realm="Biocontrol Parking API", charset="UTF-8"'],
            );
        }

        RateLimiter::clear($kalit);

        $user = $guard->user();

        if (! $user->can(config('menu_permissions.api.parking'))) {
            Log::warning('Parking API: foydalanuvchida ruxsat yo‘q', ['user_id' => $user->id, 'ip' => $ip]);

            return $this->xato('FORBIDDEN', 'Bu foydalanuvchiga Parking API ruxsati berilmagan.', 403);
        }

        return $next($request);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function xato(string $kod, string $xabar, int $status, array $headers = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], $status, $headers);
    }
}
