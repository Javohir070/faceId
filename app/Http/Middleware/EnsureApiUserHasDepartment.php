<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integratsiya API: xodim foydalanuvchining bo'limiga yoziladi, shuning uchun bo'limsiz foydalanuvchi
 * (masalan super-admin) ishlata olmaydi. `api.user` dan keyin turadi va FormRequest tekshiruvidan oldin ishlaydi.
 */
class EnsureApiUserHasDepartment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super-admin uchun bo'lim va terminal cheklovlari (global scope’lar) o'chadi: bo'limi bo'lsa ham xodim
        // hamma terminallarga yozilib, boshqa bo'lim xodimlari o'zgarardi.
        if ($user !== null && ! $user->isDepartmentScoped()) {
            return $this->rad('SUPER_ADMIN', 'Integratsiya API super-admin bilan ishlamaydi. Tashqi tizim uchun alohida foydalanuvchi oching.');
        }

        if ($user?->department_id === null) {
            return $this->rad('NO_DEPARTMENT', 'Bu foydalanuvchiga bo‘lim biriktirilmagan. Admin → Foydalanuvchilar da bo‘lim tanlang.');
        }

        return $next($request);
    }

    private function rad(string $kod, string $xabar): Response
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], 403);
    }
}
