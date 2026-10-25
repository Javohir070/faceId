<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyLiveScreenToken;
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
        $token = $this->screenToken($request);
        $faceUrl = $token !== null
            ? fn (int $id): string => route('screen.face', ['token' => $token, 'employee' => $id])
            : null;

        return Inertia::render('Admin/Live/Index', [
            'passes' => fn () => $this->liveMonitorService->recentPasses(7, $faceUrl),
            'stats' => fn () => $this->liveMonitorService->todayStats(),
            'screen' => $this->screenLinks($token),
        ]);
    }

    /**
     * Zal ekrani — "Tashrif statistikasi" (bugungi umumiy ko‘rsatkichlar).
     */
    public function stats(Request $request): Response
    {
        return Inertia::render('Admin/Live/Stats', [
            'stats' => fn () => $this->visitStatsService->today(),
            'screen' => $this->screenLinks($this->screenToken($request)),
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
     * Ochiq havola (`/screen/{token}`) orqali kelinganda — token; admin sahifasida — null.
     */
    private function screenToken(Request $request): ?string
    {
        $token = $request->attributes->get(VerifyLiveScreenToken::ATTRIBUTE);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Ochiq havolada sahifalar bir-biriga shu havolalar bilan o‘tadi (admin panelga qaytish tugmasi yo‘q).
     *
     * @return array{live_url: string, stats_url: string}|null
     */
    private function screenLinks(?string $token): ?array
    {
        if ($token === null) {
            return null;
        }

        return [
            'live_url' => route('screen.live', $token),
            'stats_url' => route('screen.stats', $token),
        ];
    }
}
