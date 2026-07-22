<?php

namespace App\Http\Controllers;

use App\Models\AccessControllerEvent;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $menuPermissions = config('menu_permissions.resources');

        $stats = [];

        if ($user->can($menuPermissions['employees'])) {
            $stats['employees'] = Employee::query()->count();
        }

        if ($user->can($menuPermissions['devices'])) {
            $stats['devices'] = Device::query()->count();
            $stats['devicesOnline'] = Device::query()->where('is_online', true)->count();
        }

        if ($user->can($menuPermissions['departments'])) {
            $stats['departments'] = Department::query()->count();
        }

        if ($user->can($menuPermissions['users'])) {
            $stats['users'] = User::query()->count();
        }

        $recentEvents = $user->can($menuPermissions['reports'])
            ? AccessControllerEvent::query()
                ->latest('event_time')
                ->limit(6)
                ->get([
                    'id',
                    'full_name',
                    'event_time',
                    'device_ip',
                    'verify_mode',
                    'attendance_status',
                ])
            : [];

        return Inertia::render('Admin/Home', [
            'stats' => $stats,
            'recentEvents' => $recentEvents,
        ]);
    }
}
