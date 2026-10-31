<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());

        return Inertia::render('Admin/Notes/Index', [
            'notes' => $this->noteService->paginate(12, $filters),
            'filters' => [
                'employee_id' => isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
                'type' => isset($filters['type']) ? (int) $filters['type'] : null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $prefill = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'dt' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $prefillEmployeeId = $prefill['employee_id'] ?? null;

        return Inertia::render('Admin/Notes/Form', [
            ...$this->formOptions(),
            'prefillEmployeeId' => $prefillEmployeeId ? (int) $prefillEmployeeId : null,
            'prefillDate' => $prefill['dt'] ?? null,
        ]);
    }

    public function store(StoreNoteRequest $request): RedirectResponse
    {
        $this->noteService->create($request->validated());

        return redirect()
            ->route('admin.notes.index')
            ->with('success', 'Izoh muvaffaqiyatli yaratildi.');
    }

    public function edit(Note $note): Response
    {
        return Inertia::render('Admin/Notes/Form', [
            'note' => $note,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note): RedirectResponse
    {
        $this->noteService->update($note, $request->validated());

        return redirect()
            ->route('admin.notes.index')
            ->with('success', 'Izoh muvaffaqiyatli yangilandi.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->noteService->delete($note);

        return redirect()
            ->route('admin.notes.index')
            ->with('success', 'Izoh o‘chirildi.');
    }

    /**
     * @return array{employees: mixed, types: list<array{value: int, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'employees' => $this->noteService->employeesForForm(),
            'types' => array_map(
                fn (NoteType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                NoteType::cases(),
            ),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'type' => ['nullable', Rule::enum(NoteType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
