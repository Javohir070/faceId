<?php

namespace App\Http\Requests\Concerns;

use App\Models\Employee;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Integratsiya API: `{jshshir}` bo'yicha xodim. So'rov API foydalanuvchisi nomidan ketadi, shuning uchun
 * BelongsToDepartmentScope boshqa bo'lim xodimini ko'rsatmaydi — u ham “topilmadi”.
 *
 * Tekshiruv `authorize()` da: topilmasa 404, yuborilgan ma'lumot noto'g'ri bo'lsa ham (422 dan oldin).
 */
trait FindsIntegrationEmployee
{
    private ?Employee $topilganXodim = null;

    public function authorize(): bool
    {
        return $this->employee() !== null;
    }

    public function employee(): ?Employee
    {
        return $this->topilganXodim ??= Employee::query()
            ->where('card', (string) $this->route('jshshir'))
            ->first();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'ok' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'Bu JSHSHIR li xodim topilmadi.'],
        ], 404));
    }
}
