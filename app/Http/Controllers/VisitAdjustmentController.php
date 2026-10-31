<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitAdjustmentRequest;
use App\Models\Group;
use App\Models\VisitAdjustment;
use App\Services\VisitAdjustmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitAdjustmentController extends Controller
{
    public function __construct(
        private readonly VisitAdjustmentService $adjustmentService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());
        $day = Carbon::parse($filters['date'] ?? now()->toDateString());

        return Inertia::render('Admin/Live/Adjustments', [
            'date' => $day->toDateString(),
            'adjustments' => $this->adjustmentService->forDate($day)->map(fn (VisitAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'entered' => $adjustment->entered,
                'male' => $adjustment->male,
                'female' => $adjustment->female,
                'group_name' => $adjustment->group?->name,
                'note' => $adjustment->note,
                'user_name' => $adjustment->user?->name,
                'created_at' => $adjustment->created_at?->format('H:i'),
            ])->values(),
            'totals' => $this->adjustmentService->totals($day),
            'groups' => Group::query()->orderBy('id')->get(['id', 'name']),
            'defaultGroupId' => $this->adjustmentService->defaultGroupId(),
            'maleShare' => VisitAdjustmentService::MALE_SHARE,
        ]);
    }

    public function store(StoreVisitAdjustmentRequest $request): RedirectResponse
    {
        $adjustment = $this->adjustmentService->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.live.adjustments.index', ['date' => $adjustment->date->toDateString()])
            ->with('success', sprintf('Statistikaga %d ta kirish qo‘shildi.', $adjustment->entered));
    }

    public function destroy(VisitAdjustment $adjustment): RedirectResponse
    {
        $date = $adjustment->date->toDateString();
        $this->adjustmentService->delete($adjustment);

        return redirect()
            ->route('admin.live.adjustments.index', ['date' => $date])
            ->with('success', 'Qo‘shilgan son o‘chirildi.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterRules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
