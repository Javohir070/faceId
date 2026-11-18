<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ParkingApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parking tizimi uchun xodimlar ro'yxati. Faqat o'qiydi. Himoya — `parking.api`
 * (panel foydalanuvchisi, HTTP Basic, `Parking API` ruxsati).
 *
 * Batafsil: docs/parking-api.md.
 */
class ParkingController extends Controller
{
    public function __construct(
        private readonly ParkingApiService $parking,
    ) {}

    /** Xodimlar: id, FISH, JSHSHIR. `search` — FISH yoki JSHSHIR ichida; sahifalanadi. */
    public function employees(Request $request): JsonResponse
    {
        $filtr = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ParkingApiService::LIMIT_MAX],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $natija = $this->parking->employees(
            $filtr['search'] ?? null,
            (int) ($filtr['limit'] ?? ParkingApiService::LIMIT_DEFAULT),
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
}
