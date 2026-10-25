<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\LiveMonitorService;
use App\Services\VisitStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class LiveMonitorController extends Controller
{
    public function __construct(
        private readonly LiveMonitorService $liveMonitorService,
        private readonly VisitStatsService $visitStatsService,
    ) {}

    public function index(Request $request): Response
    {
        $screen = $this->isScreen($request);
        $faceUrl = $screen ? fn (int $id): string => route('screen.face', $id) : null;

        return Inertia::render('Admin/Live/Index', [
            'passes' => fn () => $this->liveMonitorService->recentPasses(7, $faceUrl),
            'stats' => fn () => $this->liveMonitorService->todayStats(),
            'screen' => $this->screenLinks($screen),
        ]);
    }

    /**
     * Zal ekrani — "Tashrif statistikasi" (bugungi umumiy ko‘rsatkichlar).
     */
    public function stats(Request $request): Response
    {
        return Inertia::render('Admin/Live/Stats', [
            'stats' => fn () => $this->visitStatsService->today(),
            'screen' => $this->screenLinks($this->isScreen($request)),
        ]);
    }

    /**
     * Xodim yuz rasmi — polling javobini yengil saqlash uchun alohida, keshlanadigan so‘rov.
     * Route model binding mehmonlarni yashirgani uchun xodim shu yerda `withVisitors()` bilan topiladi.
     */
    public function face(int $employee): HttpResponse
    {
        $employee = Employee::query()->withVisitors()->findOrFail($employee);
        $image = $this->liveMonitorService->faceImage($employee);

        abort_if($image === null, 404);

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'],
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Login'siz ochiq zal ekrani (`/screen…`) orqali kelinganmi.
     */
    private function isScreen(Request $request): bool
    {
        return $request->routeIs('screen.*');
    }

    /**
     * Ochiq ekranda sahifalar bir-biriga shu havolalar bilan o‘tadi (admin panelga qaytish tugmasi yo‘q).
     *
     * @return array{live_url: string, stats_url: string}|null
     */
    private function screenLinks(bool $screen): ?array
    {
        if (! $screen) {
            return null;
        }

        return [
            'live_url' => route('screen.live'),
            'stats_url' => route('screen.stats'),
        ];
    }
}
