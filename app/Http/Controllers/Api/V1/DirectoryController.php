<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DirectoryApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tashqi tizimlar uchun ma'lumotnoma: bo'limlar, guruhlar va xodimlar.
 * Faqat o'qiydi. Himoya — HTTP Basic (`directory.api`).
 *
 * Batafsil: docs/directory-api.md.
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryApiService $directory,
    ) {}

    /** Bo'limlar (tashkilot darajasi). */
    public function departments(): JsonResponse
    {
        $bolimlar = $this->directory->departments();

        return response()->json([
            'ok' => true,
            'count' => count($bolimlar),
            'departments' => $bolimlar,
        ]);
    }

    /** Guruhlar (tarkibiy bo'linmalar). */
    public function groups(Request $request): JsonResponse
    {
        $filtr = $request->validate([
            'department_id' => ['nullable', 'integer'],
        ]);

        $guruhlar = $this->directory->groups(
            isset($filtr['department_id']) ? (int) $filtr['department_id'] : null,
        );

        return response()->json([
            'ok' => true,
            'count' => count($guruhlar),
            'groups' => $guruhlar,
        ]);
    }

    /** Xodimlar. Sahifalanadi; `updated_since` bilan faqat o'zgarganlari olinadi. */
    public function employees(Request $request): JsonResponse
    {
        $filtr = $request->validate([
            'department_id' => ['nullable', 'integer'],
            // `date` ataylab: o'qilmagan sana jim turib hamma xodimni
            // qaytarsa, tashqi tizim o'zgarmaganlarni ham qayta yozadi.
            'updated_since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.DirectoryApiService::LIMIT_MAX],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $natija = $this->directory->employees(
            isset($filtr['department_id']) ? (int) $filtr['department_id'] : null,
            isset($filtr['updated_since']) ? Carbon::parse($filtr['updated_since']) : null,
            (int) ($filtr['limit'] ?? DirectoryApiService::LIMIT_DEFAULT),
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
