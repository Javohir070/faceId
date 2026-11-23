<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntegrationEmployeeRequest;
use App\Http\Requests\UpdateIntegrationEmployeeRequest;
use App\Services\IntegrationEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Tashqi tizimlar uchun xodim qo'shish, tahrirlash, o'chirish. Himoya — `api.user:integration`
 * (panel foydalanuvchisi, HTTP Basic, `Integratsiya API` ruxsati) va bo'limi bo'lishi shart.
 *
 * Batafsil: docs/integration-api.md.
 */
class IntegrationEmployeeController extends Controller
{
    public function __construct(
        private readonly IntegrationEmployeeService $integration,
    ) {}

    /** Xodim qo'shish: bazaga, so'ng foydalanuvchining terminallariga. */
    public function store(StoreIntegrationEmployeeRequest $request): JsonResponse
    {
        $user = $request->user();
        $scheduleId = $this->integration->scheduleId($user);

        if ($scheduleId === null) {
            Log::error('Integratsiya API: INTEGRATION_API_SCHEDULE_ID sozlanmagan yoki bo‘limga mos emas', [
                'user_id' => $user->id,
            ]);

            return $this->xato(
                'NOT_CONFIGURED',
                'API sozlanmagan: ish jadvali (INTEGRATION_API_SCHEDULE_ID) yo‘q. Administratorga murojaat qiling.',
                503,
            );
        }

        $natija = $this->integration->create($user, $request->validated(), $scheduleId);

        Log::info('Integratsiya API: xodim qo‘shildi', [
            'user_id' => $user->id,
            'employee_id' => $natija['employee']->id,
        ]);

        return response()->json([
            'ok' => true,
            'employee' => $this->integration->employeePayload($natija['employee']),
            'terminals' => $natija['terminals'],
        ], 201);
    }

    /** Xodimni tahrirlash (faqat yuborilgan maydonlar), so'ng terminallarga qayta yozish. */
    public function update(UpdateIntegrationEmployeeRequest $request): JsonResponse
    {
        $natija = $this->integration->update($request->employee(), $request->validated());

        Log::info('Integratsiya API: xodim tahrirlandi', [
            'user_id' => $request->user()->id,
            'employee_id' => $natija['employee']->id,
        ]);

        return response()->json([
            'ok' => true,
            'employee' => $this->integration->employeePayload($natija['employee']),
            'terminals' => $natija['terminals'],
        ]);
    }

    private function xato(string $kod, string $xabar, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], $status);
    }
}
