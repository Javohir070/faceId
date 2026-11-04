<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OpensFormDrawer;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    use OpensFormDrawer;

    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function index(Request $request): Response
    {
        $schedules = $this->scheduleService->paginate(20);

        $schedules->getCollection()->transform(fn (Schedule $schedule): array => [
            ...$this->formFields($schedule),
            'department' => $schedule->department?->name,
            'can_manage' => $schedule->canBeManagedBy($request->user()),
        ]);

        return Inertia::render('Admin/Schedules/Index', [
            'schedules' => $schedules,
            'drawer' => $this->drawerState($request, 'schedules', function (int $id) use ($request): ?array {
                $schedule = Schedule::query()->find($id);

                return $schedule?->canBeManagedBy($request->user()) ? $this->formFields($schedule) : null;
            }),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formFields(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'enter_time' => $schedule->enter_time?->format('H:i'),
            'exit_time' => $schedule->exit_time?->format('H:i'),
        ];
    }

    /** Alohida sahifa yo‘q: qo‘shish oynasi ro‘yxatdagi o‘ng panelda ochiladi. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.schedules.index', ['create' => 1]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->create($request->validated());

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', 'Jadval muvaffaqiyatli yaratildi.');
    }

    /** Alohida ko‘rish sahifasi yo‘q — ma’lumot ro‘yxatda; eski havola ro‘yxatga qaytaradi. */
    public function show(Schedule $schedule): RedirectResponse
    {
        return redirect()->route('admin.schedules.index');
    }

    public function edit(Schedule $schedule): RedirectResponse
    {
        return redirect()->route('admin.schedules.index', ['edit' => $schedule->id]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->scheduleService->update($schedule, $request->validated());

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', 'Jadval muvaffaqiyatli yangilandi.');
    }

    public function destroy(Request $request, Schedule $schedule): RedirectResponse
    {
        abort_unless($schedule->canBeManagedBy($request->user()), 403);

        if ($error = $this->scheduleService->delete($schedule)) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', "Jadval muvaffaqiyatli o'chirildi.");
    }
}
