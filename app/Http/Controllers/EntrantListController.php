<?php

namespace App\Http\Controllers;

use App\Exports\EntrantsExport;
use App\Models\Group;
use App\Services\EntrantListService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntrantListController extends Controller
{
    public function __construct(
        private readonly EntrantListService $entrants,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/Live/Entrants', [
            'filters' => $filters,
            'days' => $this->entrants->days($filters),
            'dailyTotals' => $this->entrants->dailyTotals($filters),
            'entrants' => $this->entrants->paginate($filters),
            'groups' => Group::query()->orderBy('id')->get(['id', 'name']),
            'maxDays' => EntrantListService::MAX_DAYS,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);

        return Excel::download(
            new EntrantsExport($this->entrants->all($filters), $this->entrants->days($filters)),
            'kirganlar_'.$filters['from'].'_'.$filters['to'].'.xlsx',
        );
    }

    /**
     * Standart — oxirgi 3 kun (bugun ham). Oraliq MAX_DAYS dan oshsa, `to` dan orqaga qisqartiriladi.
     *
     * @return array{from: string, to: string, search: ?string, group_id: ?int}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate($this->filterRules());

        $to = Carbon::parse($validated['to'] ?? now()->toDateString());
        $from = Carbon::parse($validated['from'] ?? $to->copy()->subDays(2)->toDateString());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= EntrantListService::MAX_DAYS) {
            $from = $to->copy()->subDays(EntrantListService::MAX_DAYS - 1);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'search' => $validated['search'] ?? null,
            'group_id' => isset($validated['group_id']) ? (int) $validated['group_id'] : null,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function filterRules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer'],
        ];
    }
}
