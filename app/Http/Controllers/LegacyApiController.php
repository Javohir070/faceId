<?php

namespace App\Http\Controllers;

use App\Services\LegacyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Eski tizimning api.php’si o‘rnini bosadi: tana JSON, amal "action" maydonida,
 * javob har doim 200 va {"status": …, "alerts": […]} ko‘rinishida (docs/legacy-api.md).
 */
class LegacyApiController extends Controller
{
    public function __construct(
        private readonly LegacyApiService $legacyApiService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // add_employee / delete_employee terminallarga so‘rov ichida yozadi.
        set_time_limit(0);

        $payload = json_decode($request->getContent(), true);

        return response()->json(
            $this->legacyApiService->handle($payload),
            200,
            ['Access-Control-Allow-Origin' => '*'],
        );
    }
}
