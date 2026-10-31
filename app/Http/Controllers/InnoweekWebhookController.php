<?php

namespace App\Http\Controllers;

use App\Http\Requests\InnoweekParticipantsWebhookRequest;
use App\Services\Innoweek\InnoweekSyncService;
use Illuminate\Http\JsonResponse;

/**
 * Innoweek karta berishi bilan ishtirokchini darhol yuboradi — real vaqt. `innoweek:sync` (20 s) zaxira bo‘lib qoladi.
 */
class InnoweekWebhookController extends Controller
{
    public function __construct(
        private readonly InnoweekSyncService $syncService,
    ) {}

    public function __invoke(InnoweekParticipantsWebhookRequest $request): JsonResponse
    {
        $stats = $this->syncService->upsertItems(array_values($request->validated('data')));

        return response()->json([
            'status' => 'ok',
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped_staff' => $stats['skipped_staff'],
            'skipped_deleted' => $stats['skipped_deleted'],
        ]);
    }
}
