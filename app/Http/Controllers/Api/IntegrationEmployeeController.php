<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyIntegrationEmployeeRequest;
use App\Http\Requests\ShowIntegrationEmployeeRequest;
use App\Http\Requests\StoreIntegrationEmployeeRequest;
use App\Http\Requests\UpdateIntegrationEmployeeRequest;
use App\Services\IntegrationEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /** Foydalanuvchi bo'limi xodimlari. `search` — FISH yoki JSHSHIR ichida; sahifalanadi. */
    public function index(Request $request): JsonResponse
    {
        $filtr = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.IntegrationEmployeeService::LIMIT_MAX],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $natija = $this->integration->list(
            $filtr['search'] ?? null,
            (int) ($filtr['limit'] ?? IntegrationEmployeeService::LIMIT_DEFAULT),
            (int) ($filtr['page'] ?? 1),
        );

        return response()->json([
            'ok' => true,
            'count' => count($natija['items']),
            'total' => $natija['total'],
            'page' => $natija['page'],
            'limit' => $natija['limit'],
            'has_more' => $natija['has_more'],
            'employees' => $natija['items'],
        ]);
    }

    /** JSHSHIR bo'yicha bitta xodim: nomlar, holat, yuz rasmi va foydalanuvchi terminallaridagi holati. */
    public function show(ShowIntegrationEmployeeRequest $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'employee' => $this->integration->details($request->employee()),
        ]);
    }

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

    /** Xodimni hamma terminallardan, so'ng bazadan o'chirish. Terminal xatosida xodim qoladi — 502. */
    public function destroy(DestroyIntegrationEmployeeRequest $request): JsonResponse
    {
        $xodim = $request->employee();
        $natija = $this->integration->delete($xodim);

        if (! $natija['deleted']) {
            Log::warning('Integratsiya API: xodim terminaldan o‘chmadi, bazada qoldi', [
                'user_id' => $request->user()->id,
                'employee_id' => $xodim->id,
            ]);

            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'TERMINAL_ERROR',
                    'message' => 'Xodim ba’zi terminallardan o‘chirilmadi va bazada qoldi. Keyinroq qayta urinib ko‘ring.',
                ],
                'terminals' => $natija['terminals'],
            ], 502);
        }

        Log::info('Integratsiya API: xodim o‘chirildi', [
            'user_id' => $request->user()->id,
            'employee_id' => $xodim->id,
        ]);

        return response()->json(['ok' => true, 'terminals' => $natija['terminals']]);
    }

    private function xato(string $kod, string $xabar, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $kod, 'message' => $xabar]], $status);
    }
}
