<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function index(): Response
    {
        $schedules = $this->scheduleService->paginate(10);

        $schedules->getCollection()->transform(fn (Schedule $schedule): array => [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'enter_time' => $schedule->enter_time?->format('H:i'),
            'exit_time' => $schedule->exit_time?->format('H:i'),
        ]);

        return Inertia::render('Admin/Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Schedules/Form');
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $this->scheduleService->create($request->validated());

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', 'Jadval muvaffaqiyatli yaratildi.');
    }

    public function show(Schedule $schedule): Response
    {
        return Inertia::render('Admin/Schedules/Show', [
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'enter_time' => $schedule->enter_time?->format('H:i'),
                'exit_time' => $schedule->exit_time?->format('H:i'),
                'created_at' => $schedule->created_at,
            ],
        ]);
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Admin/Schedules/Form', [
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'enter_time' => $schedule->enter_time?->format('H:i'),
                'exit_time' => $schedule->exit_time?->format('H:i'),
            ],
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->scheduleService->update($schedule, $request->validated());

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', 'Jadval muvaffaqiyatli yangilandi.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->scheduleService->delete($schedule);

        return redirect()
            ->route('admin.schedules.index')
            ->with('success', "Jadval muvaffaqiyatli o'chirildi.");
    }
}
