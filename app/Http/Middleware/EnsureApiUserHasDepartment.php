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
        if ($request->user()?->department_id === null) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'NO_DEPARTMENT',
                    'message' => 'Bu foydalanuvchiga bo‘lim biriktirilmagan. Admin → Foydalanuvchilar da bo‘lim tanlang.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
