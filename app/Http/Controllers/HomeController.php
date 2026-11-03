<?php

namespace App\Http\Controllers;

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
        $devices = [];

        if ($user->can($menuPermissions['employees'])) {
            $stats['employees'] = Employee::query()->count();
        }

        if ($user->can($menuPermissions['devices'])) {
            $stats['devices'] = Device::query()->count();
            $stats['devicesOnline'] = Device::query()->where('is_online', true)->count();
            $devices = Device::query()
                ->ordered()
                ->get(['id', 'name', 'ip_address', 'type', 'is_online', 'last_ping_at'])
                ->map(fn (Device $device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'type_label' => $device->typeLabel(),
                    'is_online' => $device->is_online,
                    'last_ping_at' => $device->last_ping_at?->format('Y-m-d H:i:s'),
                ]);
        }

        if ($user->can($menuPermissions['departments'])) {
            $stats['departments'] = Department::query()->visibleTo($user)->count();
        }

        if ($user->can($menuPermissions['users'])) {
            $stats['users'] = User::query()->count();
        }

        return Inertia::render('Admin/Home', [
            'stats' => $stats,
            'devices' => $devices,
        ]);
    }
}
