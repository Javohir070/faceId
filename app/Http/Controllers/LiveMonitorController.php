<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\LiveMonitorService;
use App\Services\VisitStatsService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class LiveMonitorController extends Controller
{
    public function __construct(
        private readonly LiveMonitorService $liveMonitorService,
        private readonly VisitStatsService $visitStatsService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Live/Index', [
            'passes' => fn () => $this->liveMonitorService->recentPasses(),
            'stats' => fn () => $this->liveMonitorService->todayStats(),
        ]);
    }

    /**
     * Zal ekrani — "Tashrif statistikasi" (bugungi umumiy ko‘rsatkichlar).
     */
    public function stats(): Response
    {
        return Inertia::render('Admin/Live/Stats', [
            'stats' => fn () => $this->visitStatsService->today(),
        ]);
    }

    /**
     * Xodim yuz rasmi — polling javobini yengil saqlash uchun alohida, keshlanadigan so‘rov.
     */
    public function face(Employee $employee): HttpResponse
    {
        $image = $this->liveMonitorService->faceImage($employee);

        abort_if($image === null, 404);

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'],
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
